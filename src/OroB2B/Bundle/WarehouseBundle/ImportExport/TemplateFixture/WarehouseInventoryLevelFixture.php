<?php

namespace OroB2B\Bundle\WarehouseBundle\ImportExport\TemplateFixture;

use Oro\Component\Testing\Unit\Entity\Stub\StubEnumValue;
use Oro\Bundle\ImportExportBundle\TemplateFixture\AbstractTemplateRepository;
use Oro\Bundle\ImportExportBundle\TemplateFixture\TemplateFixtureInterface;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;

use OroB2B\Bundle\ProductBundle\Entity\Product;
use OroB2B\Bundle\ProductBundle\Entity\ProductUnit;
use OroB2B\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use OroB2B\Bundle\WarehouseBundle\Entity\Warehouse;
use OroB2B\Bundle\WarehouseBundle\Entity\WarehouseInventoryLevel;

class WarehouseInventoryLevelFixture extends AbstractTemplateRepository implements TemplateFixtureInterface
{
    /**
     * {@inheritdoc}
     */
    public function getEntityClass()
    {
        return WarehouseInventoryLevel::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->getEntityData('Example Inventory Level');
    }

    /**
     * {@inheritdoc}
     */
    protected function createEntity($key)
    {
        return new WarehouseInventoryLevel();
    }

    /**
     * @param string  $key
     * @param WarehouseInventoryLevel $entity
     */
    public function fillEntityData($key, $entity)
    {
        $product = new Product();
        $inventoryStatus = new StubEnumValue(Product::INVENTORY_STATUS_IN_STOCK, 'in stock');

        $localization = new Localization();
        $localization->setName('English');

        $name = new LocalizedFallbackValue();
        $name->setString('Product Name');

        $localizedName = new LocalizedFallbackValue();
        $localizedName->setLocalization($localization)
            ->setString('US Product Name')
            ->setFallback('system');

        $product->setSku('sku_001')
        ->setInventoryStatus($inventoryStatus)
        ->addName($name)
        ->addName($localizedName);

        $warehouse = new Warehouse();
        $warehouse->setName('Main Warehouse');
        $entity->setWarehouse($warehouse);
        $entity->setQuantity(50);

        $unitPrecision = new ProductUnitPrecision();
        $unit = new ProductUnit();
        $unit->setCode('item');
        $unitPrecision->setUnit($unit);
        $unitPrecision->setProduct($product);
        $entity->setProductUnitPrecision($unitPrecision);
    }
}
