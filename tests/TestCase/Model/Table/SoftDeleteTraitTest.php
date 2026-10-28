<?php
declare(strict_types=1);

namespace SoftDelete\Test\TestCase\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use DateTime;
use PHPUnit\Framework\TestCase;
use SoftDelete\Error\MissingColumnException;
use SoftDelete\Model\Table\SoftDeleteTrait;
use SoftDelete\ORM\SelectQuery;

class UsersTable extends Table
{
    use SoftDeleteTrait;

    public function initialize(array $config): void
    {
        $this->setTable('sd_users');
        $this->hasMany('Posts', [
            'className' => PostsTable::class,
            'foreignKey' => 'user_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
    }
}

class PostsTable extends Table
{
    use SoftDeleteTrait;

    protected string $softDeleteField = 'removed_at';

    public function initialize(array $config): void
    {
        $this->setTable('sd_posts');
        $this->belongsTo('Users', ['className' => UsersTable::class, 'foreignKey' => 'user_id']);
    }
}

class NoColumnTable extends Table
{
    use SoftDeleteTrait;

    public function initialize(array $config): void
    {
        $this->setTable('sd_nocolumn');
    }
}

class SoftDeleteTraitTest extends TestCase
{
    private UsersTable $Users;
    private PostsTable $Posts;

    protected function setUp(): void
    {
        parent::setUp();
        $db = ConnectionManager::get('test');
        $db->execute('DROP TABLE IF EXISTS sd_posts');
        $db->execute('DROP TABLE IF EXISTS sd_users');
        $db->execute('DROP TABLE IF EXISTS sd_nocolumn');
        $db->execute('CREATE TABLE sd_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50), deleted DATETIME NULL)');
        $db->execute('CREATE TABLE sd_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title VARCHAR(50), removed_at DATETIME NULL)');
        $db->execute('CREATE TABLE sd_nocolumn (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50))');
        $db->execute("INSERT INTO sd_users (id, name, deleted) VALUES (1, 'alice', NULL), (2, 'bob', NULL), (3, 'carol', '2020-01-01 00:00:00')");
        $db->execute("INSERT INTO sd_posts (id, user_id, title, removed_at) VALUES (1, 1, 'a1', NULL), (2, 1, 'a2', NULL), (3, 2, 'b1', NULL), (4, 2, 'b2', '2020-01-01 00:00:00')");

        TableRegistry::getTableLocator()->clear();
        $this->Users = TableRegistry::getTableLocator()->get('Users', ['className' => UsersTable::class, 'connection' => $db]);
        $this->Posts = TableRegistry::getTableLocator()->get('Posts', ['className' => PostsTable::class, 'connection' => $db]);
    }

    protected function tearDown(): void
    {
        TableRegistry::getTableLocator()->clear();
        parent::tearDown();
    }

    public function testFindHidesSoftDeletedRecords(): void
    {
        $this->assertInstanceOf(SelectQuery::class, $this->Users->find());
        $this->assertSame(['alice', 'bob'], $this->Users->find()->orderBy(['id'])->all()->extract('name')->toList());
        $this->assertSame(2, $this->Users->find()->count());
    }

    public function testFindWithDeletedNamedArgument(): void
    {
        $this->assertSame(3, $this->Users->find('all', withDeleted: true)->count());
    }

    public function testFindWithDeletedFinder(): void
    {
        $this->assertSame(3, $this->Users->find('withDeleted')->count());
    }

    public function testFindWithDeletedApplyOptions(): void
    {
        $this->assertSame(3, $this->Users->find()->applyOptions(['withDeleted' => true])->count());
    }

    public function testGetThrowsForSoftDeletedRecord(): void
    {
        $this->expectException(RecordNotFoundException::class);
        $this->Users->get(3);
    }

    public function testCustomSoftDeleteField(): void
    {
        $this->assertSame('removed_at', $this->Posts->getSoftDeleteField());
        $this->assertSame(3, $this->Posts->find()->count());
    }

    public function testMissingColumnThrows(): void
    {
        $table = TableRegistry::getTableLocator()->get('NoColumn', ['className' => NoColumnTable::class, 'connection' => ConnectionManager::get('test')]);
        $this->expectException(MissingColumnException::class);
        $table->getSoftDeleteField();
    }

    public function testDeleteSoftDeletesAndKeepsRow(): void
    {
        $alice = $this->Users->get(1);
        $this->assertTrue($this->Users->delete($alice));

        $this->assertSame(1, $this->Users->find()->count());
        $row = $this->Users->find('all', withDeleted: true)->where(['id' => 1])->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->deleted);
    }

    public function testDeleteCascadesSoftDeleteToDependentAssociation(): void
    {
        $alice = $this->Users->get(1);
        $this->assertTrue($this->Users->delete($alice));

        $this->assertSame(0, $this->Posts->find()->where(['user_id' => 1])->count());
        $this->assertSame(2, $this->Posts->find('all', withDeleted: true)->where(['user_id' => 1])->count());
    }

    public function testDeleteNewEntityReturnsFalse(): void
    {
        $this->assertFalse($this->Users->delete($this->Users->newEntity(['name' => 'x'])));
    }

    public function testDeleteAll(): void
    {
        $this->assertSame(2, $this->Users->deleteAll(['name IN' => ['alice', 'bob']]));
        $this->assertSame(0, $this->Users->find()->count());
        $this->assertSame(3, $this->Users->find('all', withDeleted: true)->count());
    }

    public function testHardDeleteRemovesRow(): void
    {
        $bob = $this->Users->get(2);
        $this->assertTrue($this->Users->hardDelete($bob));
        $this->assertSame(2, $this->Users->find('all', withDeleted: true)->count());
        $this->assertNull($this->Users->find('all', withDeleted: true)->where(['id' => 2])->first());
    }

    public function testHardDeleteAllOnlyRemovesSoftDeletedBeforeDate(): void
    {
        $this->Users->delete($this->Users->get(1)); // deleted now
        $this->assertSame(1, $this->Users->hardDeleteAll(new DateTime('2021-01-01'))); // only carol (2020)
        $this->assertSame(2, $this->Users->find('all', withDeleted: true)->count());
        $this->assertSame(1, $this->Users->find()->count()); // bob
    }

    public function testRestore(): void
    {
        $carol = $this->Users->find('all', withDeleted: true)->where(['id' => 3])->firstOrFail();
        $this->assertNotFalse($this->Users->restore($carol));
        $this->assertSame(3, $this->Users->find()->count());
        $this->assertNull($this->Users->get(3)->deleted);
    }

    public function testContainFiltersSoftDeletedAssociatedRecords(): void
    {
        $bob = $this->Users->find()->contain('Posts')->where(['Users.id' => 2])->firstOrFail();
        $this->assertCount(1, $bob->posts);
        $this->assertSame('b1', $bob->posts[0]->title);
    }
}
