<?php

use FpDbTest\Database;
use FpDbTest\DatabaseInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtendedDatabaseTest extends TestCase
{

  private DatabaseInterface $db;
  /**
   * @throws Exception
   */
  protected function setUp(): void
  {
    $mysqli = @new mysqli('mysql', 'root', 'password', 'database', 3306);
    if ($mysqli->connect_errno) {
      throw new Exception($mysqli->connect_error);
    }

    $this->db = new Database($mysqli);
  }


  #[DataProvider('provide_queries')]
  public function test_build_query(string $query, array $args, string $expected): void
  {
    $exception = null;
    try {
      $result = $this->db->buildQuery($query, $args);
      $this->assertEquals($expected, $result);
    } catch (Exception $e) {
      $exception = $e;
    }
    $this->assertNull($exception);
  }


  #[DataProvider('provide_incorrect_queries')]
  public function test_incorrect_build_query(string $query, array $args): void
  {
    $exception = null;
    try {
      $this->db->buildQuery($query, $args);
    } catch (Exception $e) {
      $exception = $e;
    }
    $this->assertNotNull($exception);
  }

  static function provide_queries(): array
  {
    $mysqli = @new mysqli('mysql', 'root', 'password', 'database', 3306);
    if ($mysqli->connect_errno) {
      throw new Exception($mysqli->connect_error);
    }

    $db = new Database($mysqli);
    return [
      // Data for empty case
      [
        'SELECT name FROM users WHERE id = 1',
        [],
        'SELECT name FROM users WHERE id = 1'
      ],

      // Data for placeholder without specifier
      [
        'SELECT name FROM users WHERE id = ?',
        [1],
        'SELECT name FROM users WHERE id = 1'
      ],
      [
        'SELECT name FROM users WHERE id = ? AND block_id = ?',
        [1, 5],
        'SELECT name FROM users WHERE id = 1 AND block_id = 5'
      ],
      [
        'SELECT name FROM users WHERE id = ? AND name = ?',
        [1, 'John'],
        'SELECT name FROM users WHERE id = 1 AND name = \'John\''
      ],
      [
        'SELECT name FROM users WHERE is_active = ? AND name = ?',
        [true, 'John'],
        'SELECT name FROM users WHERE is_active = 1 AND name = \'John\''
      ],
      [
        'SELECT name FROM users WHERE id = ? AND block_id = ?',
        [1, null],
        'SELECT name FROM users WHERE id = 1 AND block_id = NULL'
      ],
      [
        'SELECT * FROM products WHERE height = ? AND width = ? AND product_name = ?',
        [29.8, 10.5, 'Product 1'],
        'SELECT * FROM products WHERE height = 29.8 AND width = 10.5 AND product_name = \'Product 1\''
      ],

      // Data for int placeholders
      [
        'SELECT name FROM users WHERE id = ?d',
        [1],
        'SELECT name FROM users WHERE id = 1'
      ],
      [
        'SELECT * FROM users WHERE id = ?d AND block_id = ?d',
        [2, 5],
        'SELECT * FROM users WHERE id = 2 AND block_id = 5'
      ],
      [
        'SELECT * FROM users WHERE id = ?d AND block_id = ?d',
        [2.2, '5'],
        'SELECT * FROM users WHERE id = 2 AND block_id = 5'
      ],
      [
        'SELECT * FROM users WHERE id = ?d AND block_id = ?d',
        [2, null],
        'SELECT * FROM users WHERE id = 2 AND block_id = NULL'
      ],
      [
        'SELECT * FROM users WHERE id = ?d AND product_id IN (SELECT id FROM products WHERE price > ?d)',
        [3, '100'],
        'SELECT * FROM users WHERE id = 3 AND product_id IN (SELECT id FROM products WHERE price > 100)'
      ],

      // Data for float placeholders
      [
        'SELECT * FROM products WHERE height = ?f',
        [29.8],
        'SELECT * FROM products WHERE height = 29.8'
      ],
      [
        'SELECT * FROM products WHERE height = ?f AND width = ?f',
        [29.8, 10.5],
        'SELECT * FROM products WHERE height = 29.8 AND width = 10.5'
      ],
      [
        'SELECT * FROM products WHERE height = ?f AND width = ?f',
        [29, 10.5],
        'SELECT * FROM products WHERE height = 29 AND width = 10.5'
      ],
      [
        'SELECT * FROM products WHERE height = ?f AND width = ?f',
        ['29.8', 10.5],
        'SELECT * FROM products WHERE height = 29.8 AND width = 10.5'
      ],
      [
        'SELECT * FROM products WHERE height = ?f AND width = ?f',
        ['29.8', null],
        'SELECT * FROM products WHERE height = 29.8 AND width = NULL'
      ],
      [
        'SELECT * FROM payment WHERE total = ?f AND product_id IN (SELECT id FROM products WHERE height > ?f)',
        [398.37, '100.5'],
        'SELECT * FROM payment WHERE total = 398.37 AND product_id IN (SELECT id FROM products WHERE height > 100.5)'
      ],

      // Data for array placeholders
      [
        'SELECT * FROM products WHERE id IN (?a)',
        [[1, 2, 3]],
        'SELECT * FROM products WHERE id IN (1, 2, 3)'
      ],
      [
        'UPDATE products SET ?a WHERE id IN (?a)',
        [['name' => 'Product 1', 'price' => 100], [1, 2, 3]],
        'UPDATE products SET `name` = \'Product 1\', `price` = 100 WHERE id IN (1, 2, 3)'
      ],
      [
        'UPDATE products SET ?a WHERE id IN (?a) AND user_id IN (SELECT id FROM users WHERE id IN (?a))',
        [['name' => 'Product 1', 'price' => 100], [1, 2, 3], [1, 2, 3]],
        'UPDATE products SET `name` = \'Product 1\', `price` = 100 WHERE id IN (1, 2, 3) AND user_id IN (SELECT id FROM users WHERE id IN (1, 2, 3))'
      ],

      // Data for identifier placeholders
      [
        'SELECT ?# FROM products WHERE id = ?d',
        ['name', 1],
        'SELECT `name` FROM products WHERE id = 1'
      ],
      [
        'SELECT ?# FROM products WHERE id = ?d',
        [['name'], 1],
        'SELECT `name` FROM products WHERE id = 1'
      ],
      [
        'SELECT ?# FROM products WHERE id = ?d',
        [['name', 'price'], 1, 'price', 100],
        'SELECT `name`, `price` FROM products WHERE id = 1'
      ],
      [
        'SELECT ?# FROM products WHERE id = ?d AND ?# = ?d',
        [['name', 'price'], 1, 'price', '100'],
        'SELECT `name`, `price` FROM products WHERE id = 1 AND `price` = 100'
      ],
      [
        'UPDATE ?# SET ?a WHERE id = ?d',
        ['products', ['name' => 'Product 1', 'price' => 100], 1],
        'UPDATE `products` SET `name` = \'Product 1\', `price` = 100 WHERE id = 1'
      ],

      // Data for conditional blocks
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d}',
        [100],
        'SELECT * FROM products WHERE id = 1 AND price > 100'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d}',
        [$db->skip()],
        'SELECT * FROM products WHERE id = 1'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d AND price < ?d}',
        [100, 200],
        'SELECT * FROM products WHERE id = 1 AND price > 100 AND price < 200'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d AND price < ?d}',
        [5, 2000],
        'SELECT * FROM products WHERE id = 1 AND price > 5 AND price < 2000'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d AND price < ?d}',
        [2000, 3],
        'SELECT * FROM products WHERE id = 1 AND price > 2000 AND price < 3'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d AND name = ?}',
        [2000, 'Product 1'],
        'SELECT * FROM products WHERE id = 1 AND price > 2000 AND name = \'Product 1\''
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND name = ? AND price > ?d}',
        ['Product 1', 2000],
        'SELECT * FROM products WHERE id = 1 AND name = \'Product 1\' AND price > 2000'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d AND price < ?d}',
        [$db->skip(), 200],
        'SELECT * FROM products WHERE id = 1'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d AND price < ?d}',
        [100, $db->skip()],
        'SELECT * FROM products WHERE id = 1'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d}{ AND price < ?d}',
        [$db->skip(), 200],
        'SELECT * FROM products WHERE id = 1 AND price < 200'
      ],
      [
        'SELECT * FROM products WHERE id = 1{ AND price > ?d}{ AND price < ?d}',
        [100, $db->skip()],
        'SELECT * FROM products WHERE id = 1 AND price > 100'
      ],

      // Data for various placeholders
      [
        'SELECT ?# FROM products WHERE id = ?d AND price > ?d AND name = ? AND height = ?f AND width = ?f AND id IN (?a)',
        [['name', 'price'], 1, 100, 'Product 1', 29.8, 10.5, [1, 2, 3]],
        'SELECT `name`, `price` FROM products WHERE id = 1 AND price > 100 AND name = \'Product 1\' AND height = 29.8 AND width = 10.5 AND id IN (1, 2, 3)'
      ],
      [
        'SELECT ?# FROM products WHERE id = ?d AND price > ?d AND name = ? AND height = ? AND width = ? AND id IN (?a){ AND price < ?f}',
        [['name', 'price'], 1.2, '100', 'Product 1', 29.8, 10, [1, 2, 3], '200.45'],
        'SELECT `name`, `price` FROM products WHERE id = 1 AND price > 100 AND name = \'Product 1\' AND height = 29.8 AND width = 10 AND id IN (1, 2, 3) AND price < 200.45'
      ],
      [
        'SELECT * FROM products WHERE id = ?d AND price IS NOT ?d AND name = ? AND height = ?f AND width = ?f AND id IN (?a){ AND price < ?d}{ AND price > ?d}',
        [ 1, null, 'Product 1', 29.8, 10.5, [1, 2, 3], $db->skip(), 50],
        'SELECT * FROM products WHERE id = 1 AND price IS NOT NULL AND name = \'Product 1\' AND height = 29.8 AND width = 10.5 AND id IN (1, 2, 3) AND price > 50'
      ],

      // Data for test escaping string
      [
        'SELECT name FROM users WHERE name = ?',
        ['John\'s'],
        'SELECT name FROM users WHERE name = \'John\\\'s\''
      ],
      [
        'SELECT name FROM users WHERE name = ?',
        ['John"s'],
        'SELECT name FROM users WHERE name = \'John\\"s\''
      ],
      [
        'SELECT name FROM users WHERE name = ?',
        ['John\\s'],
        'SELECT name FROM users WHERE name = \'John\\\\s\''
      ],
      [
        'SELECT * FROM users WHERE name = ?',
        ['\' OR \'1\'=\'1'],
        'SELECT * FROM users WHERE name = \'\\\' OR \\\'1\\\'=\\\'1\''
      ]
    ];
  }

  static function provide_incorrect_queries(): array
  {
    return [
      // Data for incorrect unknown placeholders
      [
        'SELECT name FROM users WHERE id = ?',
        [[1]],
      ],
      [
        'SELECT name FROM users WHERE id = ?',
        [['1']],
      ],
      [
        'SELECT name FROM users WHERE id = ?',
        [[null]],
      ],

      // Data for incorrect int placeholders
      [
        'SELECT name FROM users WHERE id = ?d',
        [[1]],
      ],
      [
        'SELECT name FROM users WHERE id = ?d',
        [['1']],
      ],
      [
        'SELECT name FROM users WHERE id = ?d',
        [[null]],
      ],

      // Data for incorrect float placeholders
      [
        'SELECT name FROM users WHERE id = ?f',
        [[1]],
      ],
      [
        'SELECT name FROM users WHERE id = ?f',
        [['1']],
      ],
      [
        'SELECT name FROM users WHERE id = ?f',
        [[null]],
      ],

      // Data for incorrect array placeholders
      [
        'SELECT name FROM users WHERE id IN (?a)',
        [null],
      ],
      [
        'SELECT name FROM users WHERE id IN (?a)',
        [1],
      ],
      [
        'SELECT name FROM users WHERE id IN (?a)',
        ['1'],
      ],
      [
        'SELECT name FROM users WHERE id IN (?a)',
        [3.5],
      ],
      [
        'SELECT name FROM users WHERE id IN (?a)',
        [[]],
      ],
      [
        'UPDATE users SET ?a WHERE id = 1',
        [['name' => []]],
      ],

      // Data for incorrect identifier placeholders
      [
        'SELECT ?# FROM users WHERE id = 1',
        [null],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        [1],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        [1.5],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        [[null, 'name']],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        [[]],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        ['long name'],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        ['strange`name'],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        ['strange\'name'],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        [['long name', 'id']],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        [['strange\'name', 'id']],
      ],
      [
        'SELECT ?# FROM users WHERE id = 1',
        [['strange`name', 'id']],
      ],

      // Data for incorrect conditional blocks
      [
        'SELECT name FROM users WHERE id = 1{ AND price > ?d',
        [null],
      ],
      [
        'SELECT name FROM users WHERE id = 1 AND price > ?d}',
        [null],
      ],
      [
        'SELECT name FROM users WHERE id = 1{ AND {price > ?d}',
        [null],
      ],
      [
        'SELECT name FROM users WHERE id = 1{ AND price} > ?d}',
        [null],
      ],
      [
        'SELECT name FROM users WHERE id = 1{ AND {price} > ?d}',
        [null],
      ],
    ];
  }
}