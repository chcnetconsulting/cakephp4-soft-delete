# CakeSoftDelete plugin for CakePHP 5.x

## Fork

This repo is a fork of the archived [salines/cakephp4-soft-delete](https://github.com/salines/cakephp4-soft-delete)
(itself a fork of [pgbi/cakephp3-soft-delete](https://github.com/PGBI/cakephp3-soft-delete)), ported to CakePHP 5.
The package name `salines/cakephp4-soft-delete` is kept on purpose so that existing applications only need to add a
`repositories` entry; the trait, its namespace and the public methods are unchanged.

What changed for CakePHP 5 (v2.0.0):

- `Table::query()` is gone in CakePHP 5; the plugin now overrides `selectQuery()` and ships
  `SoftDelete\ORM\SelectQuery` (replacing `SoftDelete\ORM\Query`). `find()` and `get()` are built on it.
- Options arrays passed to `find()` are no longer forwarded by CakePHP 5. Use the named argument or the finder:
  `find('all', withDeleted: true)`, `find('withDeleted')` or `->applyOptions(['withDeleted' => true])`.
- Typed signatures matching `Cake\ORM\Table` (`_processDelete`, `deleteAll`, `hardDelete`, `hardDeleteAll`, `restore`).
- `MissingColumnException` extends `Cake\Core\Exception\CakeException`.
- Tests (SQLite in memory): `composer install && vendor/bin/phpunit`. Green on PHP 8.3 and 8.5 with CakePHP 5.4.

## Purpose

This CakePHP plugin enables you to make your models soft deletable.
When soft deleting an entity, it is not actually removed from your database. Instead, a `deleted` timestamp is set on the record.

## Requirements

CakePHP ^5.0 and PHP >= 8.1. For CakePHP 4 use the original package, version `~1.0`.

## Installation

Add the fork as a VCS repository and require the (unchanged) package name:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/chcnetconsulting/cakephp4-soft-delete" }
],
"require": {
    "salines/cakephp4-soft-delete": "^2.0"
}
```

## Configuration

### Make a model soft deleteable:

Use the SoftDelete trait on your model Table class:

```php
// in src/Model/Table/UsersTable.php
use SoftDelete\Model\Table\SoftDeleteTrait;

class UsersTable extends Table
{
    use SoftDeleteTrait;
    ...
```

Your soft deletable model database table should have a field called `deleted` of type DateTime with NULL as default value.
If you want to customise this field you can declare the field in your Table class.

```php
class UsersTable extends Table
{
    use SoftDeleteTrait;

    protected string $softDeleteField = 'deleted_date';
    ...
```

## Use

### Soft deleting records

`delete` and `deleteAll` functions will now soft delete records by populating `deleted` field with the date of the deletion.

```php
$this->delete($user); // $user entity is now soft deleted if UsersTable uses SoftDeleteTrait.
```

### Restoring soft deleted records

```php
// Let's suppose $user #1 is soft deleted.
$user = $this->Users->find('all', withDeleted: true)->where(['id' => 1])->first();
$this->Users->restore($user); // $user #1 is now restored.
```

### Finding records

`find`, `get` or dynamic finders (such as `findById`) will only return non soft deleted records.
To also return soft deleted records:

```php
$nonSoftDeletedRecords = $this->find('all');
$allRecords            = $this->find('all', withDeleted: true);
$allRecords            = $this->find('withDeleted');
```

### Hard deleting records

```php
$user = $this->get($userId);
$success = $this->hardDelete($user);

// mass hard delete of records soft deleted before a given date
$affectedRowsCount = $this->hardDeleteAll(new \DateTime('some date'));
```

## Soft deleting & associations

1. Soft deletion is cascaded to related models as usual (`dependent` + `cascadeCallbacks`). If related models also use
   the SoftDelete trait, they are soft deleted.
2. Soft deleted records are excluded from `contain()` results and counter caches.
