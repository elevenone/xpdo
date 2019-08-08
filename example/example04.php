<?php
require __DIR__ . '/../vendor/autoload.php';

use aphp\XPDO\Database;
use aphp\XPDO\Model;
use aphp\XPDO\Utils;

class user extends Model {
	/*
	public $id;
	public $name;
	public $email;
	*/
	static function jsonFields() {
		return [ 'email' ];
	}
}

$jsonExample = 
'{
    "glossary": {
        "title": "example glossary",
        "GlossDiv": {
            "title": "S",
            "GlossList": {
                "GlossEntry": {
                    "ID": "SGML",
                    "SortAs": "SGML",
                    "Unicode": "Thíś íś ṕŕéttӳ fúń tőő. Dő śőḿéthíńǵ főŕ ӳőúŕ ǵŕőúṕ táǵ",
                    "Acronym": "SGML",
                    "Abbrev": "ISO 8879:1986",
                    "GlossDef": {
                        "para": "A meta-markup language, used to create markup languages such as DocBook.",
                        "GlossSeeAlso": [
                            "GML",
                            "XML"
                        ]
                    },
                    "GlossSee": "markup"
                }
            }
        }
    }
}';

if (!file_exists(__DIR__ . '/sampleBase.sqlite')) {
	copy(__DIR__ . '/../tests/db/sampleBase.sqlite', __DIR__ . '/sampleBase.sqlite');
}

$db = Database::getInstance();
$db->SQLiteInit(__DIR__ . '/sampleBase.sqlite');

$user = user::newModel();

$user->name = 'name00001';
$user->email = Utils::jsonDecode($jsonExample);

$user->save();

$user2 = user::loadWithId($user->id);

print_r($user2);
echo PHP_EOL;

$user->email = [ 'hello world' => 'json' ];
$user->save();

$user3 = user::loadWithId($user->id);
print_r($user3);
echo PHP_EOL;

