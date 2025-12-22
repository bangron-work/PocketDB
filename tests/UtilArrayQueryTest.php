<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class UtilArrayQueryTest extends TestCase
{
    public function testBuildConditionSimpleEquality()
    {
        // Test that we can access the UtilArrayQuery class through the Database class
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['name' => 'John'];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test the function with a matching document
        $document = ['name' => 'John', 'age' => 30];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with non-matching document
        $document2 = ['name' => 'Jane', 'age' => 25];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testBuildConditionWithAnd()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['$and' => [['name' => 'John'], ['age' => 30]]];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with matching document
        $document = ['name' => 'John', 'age' => 30];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with non-matching document (wrong name)
        $document2 = ['name' => 'Jane', 'age' => 30];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);

        // Test with non-matching document (wrong age)
        $document3 = ['name' => 'John', 'age' => 25];
        $result3 = $db->callCriteriaFunction($id, $document3);
        $this->assertFalse($result3);
    }

    public function testBuildConditionWithOr()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['$or' => [['name' => 'John'], ['name' => 'Jane']]];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with first matching condition
        $document = ['name' => 'John', 'age' => 30];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with second matching condition
        $document2 = ['name' => 'Jane', 'age' => 25];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertTrue($result2);

        // Test with non-matching document
        $document3 = ['name' => 'Bob', 'age' => 30];
        $result3 = $db->callCriteriaFunction($id, $document3);
        $this->assertFalse($result3);
    }

    public function testBuildConditionWithNestedFields()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['user.name' => 'John'];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with matching nested field
        $document = ['user' => ['name' => 'John', 'age' => 30]];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with non-matching nested field
        $document2 = ['user' => ['name' => 'Jane', 'age' => 25]];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testBuildConditionWithMultipleOperators()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = [
            'age' => ['$gt' => 25, '$lt' => 35],
            'active' => true
        ];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with matching document
        $document = ['age' => 30, 'active' => true];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with age out of range
        $document2 = ['age' => 20, 'active' => true];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);

        // Test with inactive user
        $document3 = ['age' => 30, 'active' => false];
        $result3 = $db->callCriteriaFunction($id, $document3);
        $this->assertFalse($result3);
    }

    public function testBuildConditionWithInOperator()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['status' => ['$in' => ['active', 'pending']]];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with first matching value
        $document = ['status' => 'active'];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with second matching value
        $document2 = ['status' => 'pending'];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertTrue($result2);

        // Test with non-matching value
        $document3 = ['status' => 'inactive'];
        $result3 = $db->callCriteriaFunction($id, $document3);
        $this->assertFalse($result3);
    }

    public function testBuildConditionWithNinOperator()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['status' => ['$nin' => ['inactive', 'banned']]];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with allowed value
        $document = ['status' => 'active'];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with first excluded value
        $document2 = ['status' => 'inactive'];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);

        // Test with second excluded value
        $document3 = ['status' => 'banned'];
        $result3 = $db->callCriteriaFunction($id, $document3);
        $this->assertFalse($result3);
    }

    public function testBuildConditionWithRegex()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['email' => ['$regex' => '@example\\.com$']];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with matching email
        $document = ['email' => 'john@example.com'];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with non-matching email
        $document2 = ['email' => 'john@gmail.com'];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testBuildConditionWithExists()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['phone' => ['$exists' => true]];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with existing field
        $document = ['phone' => '123-456-7890'];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with missing field
        $document2 = ['name' => 'John'];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testBuildConditionWithExistsFalse()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['phone' => ['$exists' => false]];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with missing field
        $document = ['name' => 'John'];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with existing field
        $document2 = ['phone' => '123-456-7890'];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testBuildConditionWithSize()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['tags' => ['$size' => 3]];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with correct size
        $document = ['tags' => ['php', 'javascript', 'mysql']];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with wrong size
        $document2 = ['tags' => ['php', 'javascript']];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testBuildConditionWithMod()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = ['id' => ['$mod' => [2, 0]]]; // Even numbers
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with even number
        $document = ['id' => 4];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with odd number
        $document2 = ['id' => 5];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);
    }

    public function testBuildConditionWithComplexNested()
    {
        $db = new \PocketDB\Database(':memory:');

        $criteria = [
            '$and' => [
                ['user.active' => true],
                [
                    '$or' => [
                        ['user.role' => 'admin'],
                        ['user.role' => 'moderator']
                    ]
                ],
                ['user.age' => ['$gt' => 18]]
            ]
        ];
        $id = $db->registerCriteriaFunction($criteria);
        $this->assertIsString($id);

        // Test with fully matching document
        $document = [
            'user' => [
                'active' => true,
                'role' => 'admin',
                'age' => 25
            ]
        ];
        $result = $db->callCriteriaFunction($id, $document);
        $this->assertTrue($result);

        // Test with inactive user
        $document2 = [
            'user' => [
                'active' => false,
                'role' => 'admin',
                'age' => 25
            ]
        ];
        $result2 = $db->callCriteriaFunction($id, $document2);
        $this->assertFalse($result2);

        // Test with insufficient age
        $document3 = [
            'user' => [
                'active' => true,
                'role' => 'moderator',
                'age' => 16
            ]
        ];
        $result3 = $db->callCriteriaFunction($id, $document3);
        $this->assertFalse($result3);
    }
}
