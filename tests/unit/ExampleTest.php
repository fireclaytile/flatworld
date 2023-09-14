<?php
/**
 * Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld\tests\unit;

use Craft;
use UnitTester;
use Codeception\Test\Unit;

class ExampleTest extends Unit {
	/**
	 * @var UnitTester
	 */
	protected UnitTester $tester;

	/**
	 * @return void
	 */
	public function testExample() {
		Craft::$app->setEdition(Craft::Pro);

		$this->assertSame(Craft::Pro, Craft::$app->getEdition());
	}
}
