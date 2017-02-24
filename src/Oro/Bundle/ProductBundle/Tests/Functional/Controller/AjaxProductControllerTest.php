<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\Controller;

use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class AjaxProductControllerTest extends WebTestCase
{
    const NEW_PRODUCT_NAME = 'New default product-1 name';

    protected function setUp()
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->client->useHashNavigation(true);

        $this->loadFixtures([LoadProductData::class]);
    }

    public function testProductEditName()
    {
        /** @var Product $product1 */
        $product1 = $this->getReference(LoadProductData::PRODUCT_1);
        $this->assertEquals(LoadProductData::PRODUCT_1_DEFAULT_NAME, $product1->getName());

        $this->client->request(
            'PATCH',
            $this->getUrl('oro_product_ajax_edit_name'),
            [
                'id' => $product1->getId(),
                'productName' => self::NEW_PRODUCT_NAME,
            ]
        );
        $result = $this->client->getResponse();

        $this->assertJsonResponseStatusCodeEquals($result, 200);
        $this->assertEquals(self::NEW_PRODUCT_NAME, $product1->getName());
    }

    public function testProductEditNameEmptyParameters()
    {
        $this->client->request(
            'PATCH',
            $this->getUrl('oro_product_ajax_edit_name'),
            []
        );
        $result = $this->client->getResponse();

        $this->assertJsonResponseStatusCodeEquals($result, 400);
    }

    public function testProductEditNameMissingProduct()
    {
        /** @var Product $product8 */
        $product8 = $this->getReference(LoadProductData::PRODUCT_8);
        $id = $product8->getId() + 999999;

        $this->client->request(
            'PATCH',
            $this->getUrl('oro_product_ajax_edit_name'),
            [
                'id' => $id,
                'productName' => self::NEW_PRODUCT_NAME,
            ]
        );
        $result = $this->client->getResponse();

        $this->assertJsonResponseStatusCodeEquals($result, 400);
    }
}
