<?php

namespace Grimoire\Test;

use Grimoire\Database;
use Grimoire\Exception\NamedQueryNotFoundException;

class NamedQueriesTest extends AbstractGrimoireTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->db->registerNamedQuery(
            'named_query',
            function (Database $db, $param1, $param2, $param3 = null) {
                return [
                    'return_param1' => $param1,
                    'return_param2' => $param2,
                    'return_param3' => $param3, // optional
                ];
            },
            ['param1', 'param2',]
        );

        $this->db->registerNamedQuery(
            'named_query_with_required_params',
            function (Database $db, $param1, $param2) {
                return [
                    'return_param1' => $param1,
                    'return_param2' => $param2,
                ];
            },
            ['param1', 'param2',]
        );

        $this->db->registerNamedQuery(
            'named_query_with_optional_params',
            function (Database $db, $param1 = null) {
                return [
                    'return_param1' => $param1, // optional
                ];
            }
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->db->clearNamedQueries();
    }

    public function testNamedQueries()
    {
        $result = $this->db->runNamedQuery('named_query', ['param1' => 'val1', 'param2' => 'val2']);
        $this->assertEquals([
            'return_param1' => 'val1',
            'return_param2' => 'val2',
            'return_param3' => null,
        ], $result);
    }

    public function testRegisterSameNamedQuery()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Named query \'named_query\' already exists.');
        $this->db->registerNamedQuery(
            'named_query',
            function (Database $db, $param1, $param2) {
            }
        );
    }

    public function testNamedQueriesWithRequiredParams()
    {
        $result = $this->db->runNamedQuery('named_query_with_required_params', ['param1' => 'val1', 'param2' => 'val2']);
        $this->assertEquals([
            'return_param1' => 'val1',
            'return_param2' => 'val2',
        ], $result);
    }

    public function testNamedQueriesWithOptionalParams()
    {
        $result = $this->db->runNamedQuery('named_query_with_optional_params', []);
        $this->assertEquals([
            'return_param1' => null,
        ], $result);
    }

    public function testNamedQueriesMissingRequiredParams()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required parameter: \'param1\'');
        $this->db->runNamedQuery('named_query_with_required_params', []);
    }

    public function testNamedQueriesNotExists()
    {
        $this->expectException(NamedQueryNotFoundException::class);
        $this->expectExceptionMessage('Named query \'named_query_not_exists\' not found.');
        $this->db->runNamedQuery('named_query_not_exists');
    }

    public function testNamedQueriesFromClass()
    {
        // static
        $this->db->registerNamedQuery(
            'class_callback_static',
            [NamedQueryHelper::class, 'twoRequiredOneOptionalStatic'],
            ['param1', 'param2',]
        );

        $result = $this->db->runNamedQuery('class_callback_static', ['param1' => 'val1', 'param2' => 'val2', 'param3' => 'optional']);
        $this->assertEquals([
            'return_param1' => 'val1',
            'return_param2' => 'val2',
            'return_param3' => 'optional',
        ], $result);


        // instance
        $namedQueryHelper = new NamedQueryHelper();
        $this->db->registerNamedQuery(
            'class_callback',
            [$namedQueryHelper, 'twoRequiredOneOptional'],
            ['param1', 'param2',]
        );

        $result = $this->db->runNamedQuery('class_callback', ['param1' => 'val1', 'param2' => 'val2', 'param3' => 'optional']);
        $this->assertEquals([
            'return_param1' => 'val1',
            'return_param2' => 'val2',
            'return_param3' => 'optional',
        ], $result);
    }

}

class NamedQueryHelper
{
    public static function twoRequiredOneOptional(Database $db, $param1, $param2, $param3 = null)
    {
        return [
            'return_param1' => $param1,
            'return_param2' => $param2,
            'return_param3' => $param3, // optional
        ];
    }

    public static function twoRequiredOneOptionalStatic(Database $db, $param1, $param2, $param3 = null)
    {
        return [
            'return_param1' => $param1,
            'return_param2' => $param2,
            'return_param3' => $param3, // optional
        ];
    }
}
