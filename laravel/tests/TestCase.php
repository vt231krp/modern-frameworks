<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

// Laravel 11's MySqlSchemaState still references PDO::MYSQL_ATTR_SSL_CA,
// which PHP 8.4+ reports as deprecated. The notice is environment-only and
// unrelated to the application code, so it is ignored suite-wide.
#[IgnoreDeprecations]
abstract class TestCase extends BaseTestCase
{
    //
}
