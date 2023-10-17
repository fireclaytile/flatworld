<?php

namespace fireclaytile\flatworld\tests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use fireclaytile\flatworld\variables\FlatworldVariable;
use verbb\postie\Postie;

class FlatworldVariableTest extends Unit
{
    /**
     * @var FlatworldVariable
     */
    protected FlatworldVariable $flatworldVariable;

    /**
     * @var FlatworldProvider
     */
    protected FlatworldProvider $flatworld;

    protected function _before()
    {
        parent::_before();

        Craft::$app->setEdition(Craft::Pro);

        $this->flatworldVariable = new FlatworldVariable();

        $this->flatworld = Postie::getInstance()
            ->getProviders()
            ->getProviderByHandle('flatworld');
    }

    public function testCraftIsInstalled()
    {
        $this->assertEquals(Craft::Pro, Craft::$app->getEdition());
    }

    public function testRatesReturnsAnEmptyArrayWhenAnOrderIsNotFound()
    {
        $rates = $this->flatworldVariable->getRates(0);

        $this->assertEmpty($rates);
        $this->assertIsArray($rates);
    }

    public function testRatesReturnsAnEmptyArrayWhenAnOrderHasNoLineItems()
    {
        $orderId = $this->_createOrder();
        codecept_debug('orderId: ' . $orderId);

        $rates = $this->flatworldVariable->getRates($orderId);

        $this->assertEmpty($rates);
        $this->assertIsArray($rates);
    }

    /**
     * @incomplete This test needs to be rewritten properly. Mock the line items and the order.
     */
    public function testRatesReturnsAnArrayWhenAnOrderHasLineItems()
    {
        $orderLineItems = [
            [
                'purchasableId' => 1,
                'qty' => 1,
            ],
        ];

        $orderId = $this->_createOrder($orderLineItems);
        codecept_debug('orderId: ' . $orderId);

        $rates = $this->flatworldVariable->getRates($orderId);

        $this->assertNotEmpty($rates);
        $this->assertIsArray($rates);
    }

    /**
     * Creates an order for testing
     *
     * @param array $orderLineItems
     * @return integer|null
     */
    private function _createOrder(array $orderLineItems = []): int|null
    {
        $order = new Order();
        $order->orderStatusId = null;
        $order->setLineItems($orderLineItems);

        if (!Craft::$app->getElements()->saveElement($order)) {
            codecept_debug($order->errors);
        }

        return $order->id;
    }
}
