<?php

declare(strict_types=1);

namespace Grimoire;

use Grimoire\Result\Row;
use Grimoire\Structure\ConventionStructure;
use Grimoire\Structure\DiscoveryStructure;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class DatabaseTest extends TestCase
{
    /** @var \Mysqli */
    private $connection;

    /** @var Database */
    private $db;

    protected function setUp(): void
    {
        try {
            $mysql_host = 'localhost';
            $mysql_database = 'test_grimoire_db';
            $mysql_user = 'root';
            $mysql_password = '';

            $this->connection = new \Mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_database);

            $config = Config::builder($this->connection)
                ->setDebug(false);

            $this->db = new Database($config);
        } catch (\Exception $e) {
            echo $e->getMessage();//Remove or change message in production code
        }
    }

    public function testInstance()
    {
        $this->assertInstanceOf(Database::class, $this->db);
    }

    public function testBasic()
    {
        $expected = [
            'Adminer' => [
                'author' => 'Jakub Vrana',
                'tags' => [
                    'PHP',
                    'MySQL',
                ],
            ],
            'JUSH' => [
                'author' => 'Jakub Vrana',
                'tags' => [
                    'JavaScript',
                ],
            ],
            'Nette' => [
                'author' => 'David Grudl',
                'tags' => [
                    'PHP',
                ],
            ],
            'Dibi' => [
                'author' => 'David Grudl',
                'tags' => [
                    'PHP',
                    'MySQL',
                ],
            ],
        ];

        $data1 = [];
        foreach ($this->db->table('application') as $app) {
            $title = $app['title'];
            $data1[$title] = ['author' => $app->author['name']];
            foreach ($app->related('application_tag') as $application_tag) {
                $data1[$title]['tags'][] = $application_tag->tag['name'];
            }
        }

        $this->assertEquals($expected, $data1);

        $data2 = [];
        foreach ($this->db->table('application') as $application) {
            $title = $application['title'];
            $data2[$title] = ['author' => $application->author['name']];
            foreach ($application->related('application_tag') as $application_tag) {
                $data2[$title]['tags'][] = $application_tag->tag['name'];
            }
        }

        $this->assertEquals($expected, $data2);
    }

    public function testDetail()
    {
        $data = [];
        $application = $this->db->application[1];
        foreach ($application as $key => $val) {
            $data[] = "$key: $val";
        }

        $this->assertEquals([
            'id: 1',
            'author_id: 11',
            'maintainer_id: 11',
            'title: Adminer',
            'web: http://www.adminer.org/',
            'slogan: Database management in single PHP file',
        ], $data);
    }

    public function testSearchOrder()
    {
        $data = [];
        foreach ($this->db->application('web LIKE ?', 'http://%')->order('title')->limit(3) as $application) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
            'Dibi',
            'JUSH',
        ], $data);
    }

    public function testFindOne()
    {
        $data1 = [];
        $application = $this->db->table('application')->where('title', 'Adminer')->fetch();
        foreach ($application->application_tag('tag_id', 21) as $application_tag) {
            $data1[] = $application_tag->tag['name'];
        }

        $this->assertEquals([
            'PHP',
        ], $data1);


        $data2 = [];
        foreach ($application->application_tag('tag_id', 21) as $application_tag) {
            $data2[] = $application_tag->tag['name'];
        }

        $this->assertEquals([
            'PHP',
        ], $data2);

        $slogan = $this->db->application('title', 'Adminer')->fetch('slogan');
        $this->assertEquals('Database management in single PHP file', $slogan);
    }

    public function testToString()
    {
        $expected = [
            '1',
            '2',
            '3',
            '4',
        ];

        $data1 = [];
        foreach ($this->db->table('application') as $application) {
            $data1[] = "$application";
        }
        $this->assertEquals($expected, $data1);

        $data2 = [];
        foreach ($this->db->table('application') as $application) {
            $data2[] = "$application";
        }

        $this->assertEquals($expected, $data2);
    }

    public function testAggregation()
    {
        $count = $this->db->table('application')->count("*");
        $this->assertEquals($count, 4);

        $data = [];
        foreach ($this->db->table('application') as $application) {
            $count = $application->related('application_tag')->count("*");
            $data[] = "$application[title]: $count tag(s)";
        }

        $this->assertEquals([
            'Adminer: 2 tag(s)',
            'JUSH: 1 tag(s)',
            'Nette: 1 tag(s)',
            'Dibi: 2 tag(s)',
        ], $data);
    }

    public function testSubQuery()
    {
        $data = [];

        $unknownBorn = $this->db->author('born', null); // authors with unknown date of born
        foreach ($this->db->application('author_id', $unknownBorn) as $application) { // their applications
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
            'JUSH',
            'Nette',
            'Dibi',
        ], $data);
    }

    public function testDiscovery()
    {
        $config = Config::builder($this->connection)
            ->setStructure(new DiscoveryStructure($this->connection, null, '%s_id'));
        $db = new Database($config);

        $data = [];
        foreach ($db->table('application') as $application) {
            $title = $application['title'];
            $data[$title] = ['author' => $application->author['name']];
            foreach ($application->related('application_tag') as $application_tag) {
                $data[$title]['tags'][] = $application_tag->tag['name'];
            }
        }

        $expected = [
            'Adminer' => [
                'author' => 'Jakub Vrana',
                'tags' => [
                    'PHP',
                    'MySQL',
                ],
            ],
            'JUSH' => [
                'author' => 'Jakub Vrana',
                'tags' => [
                    'JavaScript',
                ],
            ],
            'Nette' => [
                'author' => 'David Grudl',
                'tags' => [
                    'PHP',
                ],
            ],
            'Dibi' => [
                'author' => 'David Grudl',
                'tags' => [
                    'PHP',
                    'MySQL',
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }

    public function testCache()
    {
        $_SESSION = []; // not session_start() - headers already sent

        $config = Config::builder($this->connection)
            ->setCache(new SessionCache('Grimoire'));
        $cache = new Database($config);

        $applications = $cache->table('application');
        $application = $applications->fetch();
        $title = $application['title'];
        $name = $application->author['name'];
        // get all columns with no cache
        $this->assertEquals('SELECT * FROM application', $applications);
        $applications->__destruct();

        $applications = $cache->table('application');
        $application = $applications->fetch();
        // get only title and author_id
        $this->assertEquals('SELECT id, title, author_id FROM application', $applications);
        $application['slogan']; // script changed and now we want also slogan
        // all columns must have been retrieved to get slogan
        $this->assertEquals('SELECT * FROM application', $applications);
        $applications->__destruct();


        $applications = $cache->table('application');
        $applications->fetch();
        // next time, get only title, author_id and slogan
        $this->assertEquals('SELECT id, title, author_id, slogan FROM application', $applications);
    }

    public function testUpdate()
    {
        $id = 5; // auto_increment is disabled in demo
        $application = $this->db->table('application')->insert([
            'id' => $id,
            //'author_id' => $this->db->row('author',12),
            'author_id' => $this->db->author[12],
            'title' => new Literal("'Texy'"),
            'web' => '',
            'slogan' => 'The best humane Web text generator',
        ]);
        $application_tag = $application->related('application_tag')->insert(['tag_id' => 21]);

        // retrieve the really stored value
        $application = $this->db->application[$id];
        $this->assertEquals('Texy', $application['title']);

        $application['web'] = "http://texy.info/";
        $this->assertEquals('1 row updated.', $application->update() . ' row updated.');
        $this->assertEquals('http://texy.info/', $this->db->application[$id]['web']);

        $this->db->application_tag('application_id', 5)->delete(); // foreign keys may be disabled
        $this->assertEquals('1 row deleted.', $application->delete() . ' row deleted.');
        $this->assertEquals('0 rows found.', count($this->db->application('id', $id)) . ' rows found.');
    }

    public function testPairs()
    {
        $this->assertEquals([
            1 => 'Adminer',
            4 => 'Dibi',
            2 => 'JUSH',
            3 => 'Nette',
        ],
            $this->db->table('application')->order('title')->fetchPairs('id', 'title')
        );

        $this->assertEquals([
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
        ],
            $this->db->table('application')->order('id')->fetchPairs('id', 'id')
        );
    }

    public function testVia()
    {
        $data = [];
        foreach ($this->db->table('author') as $author) {
            $applications = $author->related('application')->via('maintainer_id');
            foreach ($applications as $application) {
                $data[] = "$author[name]: $application[title]";
            }
        }

        $this->assertEquals([
            'Jakub Vrana: Adminer',
            'David Grudl: Nette',
            'David Grudl: Dibi',
        ], $data);

        $this->assertEquals('SELECT * FROM application WHERE (application.maintainer_id IN (11, 12))', $applications);
    }

    public function testJoin()
    {
        $data = [];
        foreach ($this->db->table('application')->order('author.name, title') as $application) {
            $data[] = $application->author['name'] . ': ' . $application['title'];
        }

        foreach (
            $this->db->application_tag('application.author.name', 'Jakub Vrana')->group(
                'application_tag.tag_id'
            ) as $application_tag
        ) {
            $data[] = $application_tag->tag['name'];
        }

        $this->assertEquals([
            'David Grudl: Dibi',
            'David Grudl: Nette',
            'Jakub Vrana: Adminer',
            'Jakub Vrana: JUSH',
            'PHP',
            'MySQL',
            'JavaScript',
        ], $data);
    }

    public function testWhere()
    {
        $data = [];
        foreach (
            [
                $this->db->application('id', 4),
                $this->db->application('id < ?', 4),
                $this->db->application('id < ?', [4]),
                $this->db->application('id', [1, 2]),
                $this->db->application('id', null),
                $this->db->application('id', $this->db->table('application')),
                $this->db->application('id < ?', 4)->where('maintainer_id IS NOT NULL'),
                $this->db->application(['id < ?' => 4, 'author_id' => 12]),
            ] as $result
        ) {
            $data[] = implode(
                ', ',
                array_keys(iterator_to_array($result->order('id')))
            ); // aggregation("GROUP_CONCAT(id)") is not available in all drivers
        }

        $this->assertEquals([
            '4',
            '1, 2, 3',
            '1, 2, 3',
            '1, 2',
            '',
            '1, 2, 3, 4',
            '1, 3',
            '3',
        ], $data);
    }

    public function testMultiple()
    {
        $data = [];
        $application = $this->db->application[1];
        foreach (
            $application->related('application_tag')
                ->select('application_id', 'tag_id')
                ->order("application_id DESC", "tag_id DESC")
            as $application_tag
        ) {
            $data[] = "$application_tag[application_id] $application_tag[tag_id]";
        }

        $this->assertEquals([
            '1 22',
            '1 21',
        ], $data);
    }

    public function testOffset()
    {
        $data = [];

        $application = $this->db->application[1];
        foreach ($application->related('application_tag')->order('tag_id')->limit(1, 1) as $application_tag) {
            $data[] = $application_tag->tag['name'];
        }

        foreach ($this->db->table('application') as $application) {
            foreach ($application->related('application_tag')->order('tag_id')->limit(1, 1) as $application_tag) {
                $data[] = $application_tag->tag['name'];
            }
        }

        $this->assertEquals([
            'MySQL',
            'MySQL',
            'MySQL',
        ], $data);
    }

    public function testTransaction()
    {
        $data = [];
        $this->db->beginTransaction();
        $this->db->table('tag')->insert(['id' => 99, 'name' => 'Test']);
        $data[] = (string)$this->db->tag[99];
        $this->db->rollback();
        $data[] = (string)$this->db->tag[99];

        $this->assertEquals([
            99,
            null,
        ], $data);
    }

    public function testUnion()
    {
        $data = [];
        $applications = $this->db->table('application')->select('id')->order('id DESC')->limit(2);
        $tags = $this->db->table('tag')->select('id')->order('id')->limit(2);
        foreach ($applications->union($tags)->order("id DESC") as $row) {
            $data[] = $row['id'];
        }

        $this->assertEquals([
            22,
            21,
            4,
            3,
        ], $data);
    }

    public function testArrayOffset()
    {
        $where = [
            'author_id' => '11',
            'maintainer_id' => null,
        ];

        $this->assertEquals(2, $this->db->application[$where]['id']);

        $applications = $this->db->table('application')->order('id');
        $this->assertEquals(2, $applications[$where]['id']);
    }

    public function testExtended()
    {
        $data = [];
        $application = $this->db->application[3];
        $application->related('application_tag')->insert(['tag_id' => 22], ['tag_id' => 23]);
        foreach ($application->related('application_tag')->order("tag_id DESC") as $application_tag) {
            $data[] = "$application_tag[application_id] $application_tag[tag_id]";
        }
        $application->application_tag('tag_id', [22, 23])->delete();

        $this->assertEquals([
            '3 23',
            '3 22',
            '3 21',
        ], $data);
    }

    public function testSimpleUnion()
    {
        $data = [];
        $applications = $this->db->table('application')->select('id');
        $tags = $this->db->table('tag')->select('id');
        foreach ($applications->union($tags)->order("id DESC") as $row) {
            $data[] = $row['id'];
        }

        $this->assertEquals([
            23,
            22,
            21,
            4,
            3,
            2,
            1,
        ], $data);
    }

    public function testInsertUpdate()
    {
        $data = [];
        for ($i = 0; $i < 2; $i++) {
            $data[] = $this->db->table('application')->insertUpdate(['id' => 5],
                ['author_id' => 12, 'title' => 'Texy', 'web' => "", 'slogan' => "$i"]);
        }
        $application = $this->db->application[5];
        $data[] = $application->related('application_tag')->insertUpdate(['tag_id' => 21], []);
        $this->db->application('id', 5)->delete();

        $this->assertEquals([
            1,
            2,
            1,
        ], $data);
    }

    public function testPrefix()
    {
        $config = Config::builder($this->connection)
            ->setStructure(new ConventionStructure('id', '%s_id', '%s', 'prefix_'));

        $prefix = new Database($config);
        $applications = $prefix->application('author.name', 'Jakub Vrana');

        $this->assertEquals(
            'SELECT prefix_application.* FROM prefix_application LEFT JOIN prefix_author AS author ON prefix_application.author_id = author.id WHERE (author.name = \'Jakub Vrana\')',
            $applications
        );
    }

    public function testLock()
    {
        $this->assertEquals('SELECT * FROM application FOR UPDATE', $this->db->table('application')->lock());
    }

    public function testBackJoin()
    {
        $data = [];
        foreach (
            $this->db->table('author')->select(
                "author.*, COUNT(DISTINCT application:application_tag:tag_id) AS tags"
            )->group("author.id")->order("tags DESC") as $autor
        ) {
            $data[] = "$autor[name]: $autor[tags]";
        }

        $this->assertEquals([
            'Jakub Vrana: 3',
            'David Grudl: 2',
        ], $data);
    }

    public function testIn()
    {
        $this->assertEquals(0, $this->db->application('maintainer_id', [])->count("*"));
        $this->assertEquals(1, $this->db->application('maintainer_id', [11])->count("*"));
        $this->assertEquals(2, $this->db->application("NOT maintainer_id", [11])->count("*"));
        $this->assertEquals(3, $this->db->application("NOT maintainer_id", [])->count("*"));
    }

    public function testInMulti()
    {
        $data = [];
        foreach ($this->db->table('author')->order('id') as $author) {
            foreach (
                $this->db->application_tag('application_id', $author->related('application'))->order(
                    "application_id, tag_id"
                ) as $application_tag
            ) {
                $data[] = "$author: $application_tag[application_id]: $application_tag[tag_id]";
            }
        }

        $this->assertEquals([
            '11: 1: 21',
            '11: 1: 22',
            '11: 2: 23',
            '12: 3: 21',
            '12: 4: 21',
            '12: 4: 22',
        ], $data);
    }

    public function testLiteral()
    {
        $data = [];
        foreach ($this->db->table('author')->select(new Literal('? + ?', 1, 2))->fetch() as $val) {
            $data[] = $val;
        }

        $this->assertEquals([
            3,
        ], $data);
    }

    public function testRowSet()
    {
        $application = $this->db->application[1];
        $application->author = $this->db->author[12];
        $this->assertEquals(1, $application->update());
        $application->update(['author_id' => 11]);
    }

    public function testRowClass()
    {
        // update config
        $cfg = $this->db->getConfig();
        $cfg->setRowClass(TestRow::class);

        $application = $this->db->application[1];
        $this->assertEquals('Adminer', $application['test_title']);
        $this->assertEquals('Jakub Vrana', $application->author['test_name']);

        $this->db->rowClass = Row::class;
    }

    public function testDateTime()
    {
        $date = new \DateTime("2011-08-30");

        $this->db->table('application')->insert([
            'id' => 5,
            'author_id' => 11,
            'title' => $date,
            'slogan' => new Literal('?', $date),
        ]);

        $application = $this->db->table('application')->where("title = ?", $date)->fetch();
        $this->assertEquals('2011-08-30 00:00:00', $application['slogan']);
        $application->delete();
    }

    public function testInNull()
    {
        $data = [];
        foreach ($this->db->application('maintainer_id', [11, null]) as $application) {
            $data[] = $application['id'];
        }
        $this->assertEquals([
            1,
            2,
        ], $data);
    }

    public function testStructure()
    {
        $config = Config::builder($this->connection)
            ->setStructure(new SoftwareConventionStructure());

        $convention = new Database($config);
        $maintainer = $convention->application[1]->maintainer;
        $this->assertEquals('Jakub Vrana', $maintainer['name']);

        $data = [];
        foreach ($maintainer->related('application')->via('maintainer_id') as $application) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
        ], $data);
    }

    public function testUpdatePrimary()
    {
        $application = $this->db->table('tag')->insert(['id' => 24, 'name' => 'HTML']);
        $this->assertEquals(24, $application['id']);
        $application['id'] = 25;
        $this->assertEquals(25, $application['id']);
        $this->assertEquals(1, $application->update());
        $this->assertEquals(1, $application->delete());
    }

    public function testMultiResultLoop()
    {
        $data = [];
        $application = $this->db->application[1];
        for ($i = 0; $i < 4; $i++) {
            $data[] = count($application->related('application_tag'));
        }

        $this->assertEquals([
            2,
            2,
            2,
            2,
        ], $data);
    }

    public function testAnd()
    {
        $data = [];
        foreach ($this->db->application('author_id', 11)->and('maintainer_id', 11) as $application) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
        ], $data);
    }

    public function testOr()
    {
        $data = [];
        foreach ($this->db->application('author_id', 12)->or('maintainer_id', 11)->order('title') as $application) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
            'Dibi',
            'Nette',
        ], $data);
    }

    public function testParens()
    {
        $applications = $this->db->table('application')
            ->where('(author_id', 11)->and('maintainer_id', 11)->where(')')
            ->or('(author_id', 12)->and('maintainer_id', 12)->where(')');

        $data = [];
        foreach ($applications->order('title') as $application) {
            $data[] = $application['title'];
        }

        $this->assertEquals([
            'Adminer',
            'Dibi',
            'Nette',
        ], $data);
    }

    public function testThen()
    {
        $data = [
            'authors' => [],
            'tags' => []
        ];

        $db = $this->db;
        Database::then(function () use ($db, &$data) {
            $db->table('author')
                ->order("id")
                ->then(function ($authors) use (&$data) {
                    if (count($authors) > 0) {
                        foreach ($authors as $author) {
                            $data['authors'][] = $author['name'];
                        }
                    }
                });

            $db->table('application_tag')
                ->order("application_id, tag_id")
                ->thenForeach(function ($application_tag) use (&$data) {
                    Database::then(
                        $application_tag->application,
                        $application_tag->tag,
                        function ($application, $tag) use (&$data) {
                            $data['tags'][] = $application['title'] . ': ' . $tag['name'];
                        }
                    );
                });
        });

        $this->assertEquals([
            'authors' => [
                'Jakub Vrana',
                'David Grudl',
            ],
            'tags' => [
                'Adminer: PHP',
                'Adminer: MySQL',
                'JUSH: JavaScript',
                'Nette: PHP',
                'Dibi: PHP',
                'Dibi: MySQL',
            ],
        ], $data);
    }
}

/**
 * @internal
 */
class TestRow extends Row
{
    function offsetExists($key): bool
    {
        return parent::offsetExists(preg_replace('~^test_~', '', $key));
    }

    #[\ReturnTypeWillChange]
    function offsetGet($key)
    {
        return parent::offsetGet(preg_replace('~^test_~', '', $key));
    }
}

/**
 * @internal
 */
class SoftwareConventionStructure extends ConventionStructure
{
    function getReferencedTable(string $name, string $table): string
    {
        switch ($name) {
            case 'maintainer':
                return parent::getReferencedTable('author', $table);
        }
        return parent::getReferencedTable($name, $table);
    }
}

/**
 * @internal
 */
class SessionCache implements CacheInterface
{
    /** @var string */
    private $sessionKey = '';

    public function __construct(string $key, bool $autostart = false)
    {
        $this->sessionKey = $key;
        if ($autostart === true && (session_status() === PHP_SESSION_NONE)) {
            session_start();
        }
    }

    public function get($key, $default = null)
    {
        return $_SESSION[$this->sessionKey][$key] ?? $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $_SESSION[$this->sessionKey][$key] = $value;
        return ($_SESSION[$this->sessionKey][$key] === $value);
    }

    public function delete($key): bool
    {
        unset($_SESSION[$this->sessionKey][$key]);
        return !isset($_SESSION[$this->sessionKey][$key]);
    }

    public function clear(): bool
    {
        unset($_SESSION[$this->sessionKey]);
        return !isset($_SESSION[$this->sessionKey]);
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return $_SESSION[$this->sessionKey] ?? [];
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $_SESSION[$this->sessionKey] = $values;

        return empty(
        array_diff(
            (array)$values,
            (array)$_SESSION[$this->sessionKey]
        )
        );
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            unset($_SESSION[$this->sessionKey][$key]);
        }
        $diff = array_intersect_key(array_flip((array)$keys), $_SESSION[$this->sessionKey]);
        return count($diff) !== count((array)$keys);
    }

    public function has($key): bool
    {
        return isset($_SESSION[$this->sessionKey][$key]);
    }
}
