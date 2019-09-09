<?php
namespace RT\Test\Sample;
use aphp\XPDO\Database;
use aphp\XPDO\Model;
use aphp\XPDO\ModelConfig;
use aphp\XPDO\Utils;
use aphp\XPDO\XPDOException;

class Category extends Model {
	static function tableName() {
		return 'category';
	}
	static function relations() {
		return [
			'books' => 'this->id *-** Book->category_id'
		];
	}
}

class Book extends Model {
	static function tableName() {
		return 'book';
	}
	static function relations() {
		return [
			'category' => 'this->category_id ** Category->id',
			'category_rt' => 'this->category_id ** RT\Test\Sample\Category->id',
			'tags' => [
				'this->id *-** TagBook->book_id', 
				'RT\Test\Sample\TagBook->tag_id ** Tag->id'
			],
			'invalid1' => 'invalid1',
			'invalid2' => [ 'invalid1','invalid2']
		];
	}
}

class Tag extends Model {
	static function tableName() {
		return 'tag';
	}
	static function relations() {
		return [
			'books' => [
				'this->id *-** RT\Test\Sample\TagBook->tag_id', 
				'TagBook->book_id ** Book->id'
			]
		];
	}
	public $id = null; // special for XPDOException::nullField case
}

class TagBook extends Model {
	static function tableName() {
		return 'tagBook';
	}
	static function keyField() {
		return null;
	}
}

// --- --- --- --- --- --- --- --- ---

class RelationTest extends \Base_TestCase 
{
	// STATIC
	public static function setUpBeforeClass() {

	}
	public static function tearDownAfterClass() {

	}

	// tests
	public function test_read() {
		// auto
		ModelConfig::$modelClass_relation_namespace = 'auto';

		$book = Book::loadWithField('name', 'Role of Religion');
		$category = $book->relation()->category;

		$this->assertTrue( is_a($category, Category::class));
		$this->assertTrue( $category->name == 'capitalism' );

		$books = $category->relation()->books;
		$this->assertTrue( is_a($books[0], Book::class) );
		$this->assertTrue( $books[0]->name == 'Social mobility' );

		// manual namespace
		ModelConfig::$modelClass_relation_namespace = 'RT\Test\Sample';

		$book = Book::loadWithField('name', 'Role of Religion');
		$category = $book->relation()->category;

		$this->assertTrue( is_a($category, Category::class));
		$this->assertTrue( $category->name == 'capitalism' );

		$books = $category->relation()->books;
		$this->assertTrue( is_a($books[0], Book::class) );
		$this->assertTrue( $books[0]->name == 'Social mobility' );
		//
		ModelConfig::$modelClass_relation_namespace = 'auto';
	}

	public function test_relations_setFields() {
		$category = Category::loadWithId(1);

		$r = $category->relation();
		$r->_propertyCache = false;

		// to many
		$books = $r->setFields(['name'])->books;
		$this->assertTrue( $books[0]->_model_loadedFields == ['name']);
		$books = $r->setFields(['id'])->books;
		$this->assertTrue( $books[0]->_model_loadedFields == ['id']);
		
		// default
		$books = $r->books;
		$this->assertTrue( $books[0]->_model_loadedFields == []);

		// many to many
		$tag = Tag::loadWithId(2);
		$r = $tag->relation();
		$r->_propertyCache = false;

		$books = $r->setFields(['name'])->books;
		$this->assertTrue( $books[0]->_model_loadedFields == ['name']);
		$books = $r->setFields(['id'])->books;
		$this->assertTrue( $books[0]->_model_loadedFields == ['id']);

		// to one 
		$book = Book::loadWithId(1);
		$r = $book->relation();
		$r->_propertyCache = false;

		$category = $r->setFields(['name'])->category;
		$this->assertTrue( $category->_model_loadedFields == ['name']);
		$category = $r->setFields(['id'])->category;
		$this->assertTrue( $category->_model_loadedFields == ['id']);
	}

	public function test_relations_order() {
		$category = Category::loadWithId(1);

		$r = $category->relation();
		$r->_propertyCache = false;

		// to many
		$books = $r->orderBy('name')->books;
		$this->assertTrue( $books[0]->name == 'Honor');
		$books = $r->orderBy('name', false)->books;
		$this->assertTrue( $books[0]->name == 'Motherhood');

		// to many - 2
		$books = $r->orderBy('name')->books;
		$mock = [];
		foreach ($books as $book) {
			$b = clone $book;
			$b->save();
			$mock[] = $b;
		}
		$books = $r->orderBy('name')->orderBy('id', false)->books;
		$this->assertTrue( $books[5]->name == 'Motherhood' &&  $books[5]->id == '1');
		$this->assertTrue( $books[4]->name == 'Motherhood');

		// many to many
		$tag = Tag::loadWithId(2);
		$r = $tag->relation();
		$r->_propertyCache = false;

		$books = $r->orderBy('name')->books;
		$this->assertTrue( $books[0]->name == 'Motherhood');
		$books = $r->orderBy('name', false)->books;
		$this->assertTrue( $books[0]->name == 'Social mobility');

		// mock
		foreach ($mock as $book) {
			$book->delete();
		}
	}

	public function test_write_toOne() {
		$book = Book::loadWithField('name', 'Role of Religion');
		$category = $book->relation()->category;
		$this->assertTrue( is_a($category, Category::class));
		$this->assertTrue( $category->name == 'capitalism' );

		$book->relation()->category = null;
		$category = $book->relation()->category;
		$this->assertTrue( $category == null );
		//
		$book->save();
		//
		$book2 = Book::loadWithField('name', 'Role of Religion');
		$this->assertTrue( $book2->category_id == null );
		
		$category = Category::loadWithField('name', 'capitalism');
		$this->assertTrue( is_a($category, Category::class));
		
		$book2->relation()->category_rt = $category;
		$this->assertTrue( $book2->relation()->category_rt == $category );
		
		$this->assertTrue( $book2->category_id == 3 );
		$book2->save();
		//
		$book3 = Book::loadWithField('name', 'Role of Religion');
		$this->assertTrue( $book3->category_id == 3 );
	}

	public function test_write_toMany() {
		$category = Category::loadWithField('id', 1);
		$this->assertTrue( is_a($category, Category::class));

		$books = $category->relation()->books;
		$this->assertTrue( count($books) == 3 );

		// add book to category
		$newBook = Book::loadWithField('name', 'Role of Religion');
		$this->assertTrue( is_a($newBook, Book::class));

		$category->relation()->toManyAdd('books', $newBook);
		$books = $category->relation()->books;
		$this->assertTrue( count($books) == 4 );

		$category2 = Category::loadWithField('id', 1);
		$books = $category2->relation()->books;
		$this->assertTrue( count($books) == 4 );

		// remove books
		$b1 = $books[2];
		$b2 = $books[0];
		$category2->relation()->toManyRemove('books', $b1);
		$books = $category2->relation()->books;
		$this->assertTrue( count($books) == 3 );
		
		$category2->relation()->toManyRemove('books', $b2);
		$books = $category2->relation()->books;
		$this->assertTrue( count($books) == 2 );

		$category3 = Category::loadWithField('id', 1);
		$books = $category3->relation()->books;
		$this->assertTrue( count($books) == 2 );

		// remove books empty
		$category3->relation()->toManyRemoveAll('books');
		$books = $category3->relation()->books;
		$this->assertTrue( count($books) == 0 );

		$category4 = Category::loadWithField('id', 1);
		$books = $category4->relation()->books;
		$this->assertTrue( count($books) == 0 );
	}

	public function test_read_manyToMany() {
		$book = Book::loadWithField('name', 'Motherhood');
		$this->assertTrue( is_a($book, Book::class) );
		$tags = $book->relation()->tags;
		
		Utils::sort($tags, 'id');

		$this->assertTrue( count($tags) == 2 );
		$this->assertTrue( $tags[0]->name == 'much' );
		$this->assertTrue( $tags[1]->name == 'question' );
		//
		$books = $tags[0]->relation()->books;
		$this->assertTrue( count($books) == 2 );

		Utils::sort($books, 'id');

		$this->assertTrue( $books[0]->name == 'Motherhood' );
		$this->assertTrue( $books[1]->name == 'Social mobility' );
	}

	public function test_write_manyToMany() {
		$book = Book::loadWithField('name', 'Motherhood');
		$this->assertTrue( is_a($book, Book::class) );
		$tag = Tag::loadWithField('name', 'blog');
		$this->assertTrue( is_a($tag, Tag::class) );

		$tags = $book->relation()->tags;
		$this->assertTrue( count($tags) == 2 );

		// add
		$book->relation()->toManyAdd('tags', $tag);
		$tags = $book->relation()->tags;
		$this->assertTrue( count($tags) == 3 );

		// remove
		$tag = Tag::loadWithField('name', 'question');
		$this->assertTrue( is_a($tag, Tag::class) );

		$book->relation()->toManyRemove('tags', $tag);
		$tags = $book->relation()->tags;
		$this->assertTrue( count($tags) == 2 );

		// remove all
		$book->relation()->toManyRemoveAll('tags');
		$tags = $book->relation()->tags;
		$this->assertTrue( count($tags) == 0 );
	}

	public function test_write_manyToMany_new() {
		$tag = Tag::newModel();
		$tag->name = 'awesome';
		$tag->save();

		$books = $tag->relation()->books;
		$this->assertTrue( count($books) == 0 );

		$books = Book::loadAllWithWhereQuery('id IN (6, 7, 3)', []);
		$this->assertTrue( count($books) == 3 );

		$tag->relation()->toManyAddAll('books', $books);
		$books = $tag->relation()->books;
		$this->assertTrue( count($books) == 3 );
	}

	public function test_exception() {
		// invalid syntax
		$book = Book::loadWithId(1);
		try {
			$r = $book->relation()->invalid1;
			$this->assertTrue(false);
		} catch (XPDOException $e) {
			$this->assertContains('relation syntax', $e->getMessage() );
		}
		try {
			$r = $book->relation()->invalid2;
			$this->assertTrue(false);
		} catch (XPDOException $e) {
			$this->assertContains('relation syntax (manyToMany)', $e->getMessage() );
		}
		// toMany relation is readonly
		try {
			$book->relation()->tags = [];
			$this->assertTrue(false);
		} catch (XPDOException $e) {
			$this->assertContains('toMany relation is readonly', $e->getMessage() );
		}
		// undefined get
		try {
			$r = $book->relation()->norelation;
			$this->assertTrue(false);
		} catch (XPDOException $e) {
			$this->assertContains('undefined relation', $e->getMessage() );
		}
		// undefined set
		try {
			$book->relation()->norelation = 11;
			$this->assertTrue(false);
		} catch (XPDOException $e) {
			$this->assertContains('undefined relation', $e->getMessage() );
		}
		// undefined toManyRemoveAll
		try {
			$book->relation()->toManyRemoveAll('norelation');
			$this->assertTrue(false);
		} catch (XPDOException $e) {
			$this->assertContains('undefined relation', $e->getMessage() );
		}
		// undefined toManyRemove
		try {
			$book->relation()->toManyRemove('norelation', $book);
			$this->assertTrue(false);
		} catch (XPDOException $e) {
			$this->assertContains('undefined relation', $e->getMessage() );
		}
		// undefined toManyAdd
		try {
			$book->relation()->toManyAdd('norelation', $book);
			$this->assertTrue(false);
		} catch (XPDOException $e) {
			$this->assertContains('undefined relation', $e->getMessage() );
		}
	}
}
