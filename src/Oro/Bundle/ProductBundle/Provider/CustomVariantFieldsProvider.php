<?php

namespace Oro\Bundle\ProductBundle\Provider;

use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

class CustomVariantFieldsProvider
{
    /**
     * @var ConfigProvider
     */
    protected $extendConfigProvider;

    /**
     * @var ConfigProvider
     */
    protected $entityConfigProvider;

    /**
     * @param ConfigProvider $extendConfigProvider
     * @param ConfigProvider $entityConfigProvider
     */
    public function __construct(ConfigProvider $extendConfigProvider, ConfigProvider $entityConfigProvider)
    {
        $this->extendConfigProvider = $extendConfigProvider;
        $this->entityConfigProvider = $entityConfigProvider;
    }

    /**
     * @param string $entityName
     * @return array
     */
    public function getEntityCustomFields($entityName)
    {
        $customFields = [];
        $extendConfigs = $this->extendConfigProvider->getConfigs($entityName);

        foreach ($extendConfigs as $extendConfig) {
            if ($extendConfig->get('owner') !== ExtendScope::OWNER_CUSTOM) {
                continue;
            }

            if (!$extendConfig->in('state', [ExtendScope::STATE_ACTIVE, ExtendScope::STATE_UPDATE])) {
                continue;
            }

            /** @var FieldConfigId $configId */
            $configId = $extendConfig->getId();

            if (!$this->isFieldTypeAllowed($configId->getFieldType())) {
                continue;
            }

            $entityConfig = $this->entityConfigProvider
                ->getConfigById($configId);


            $customFields[$configId->getFieldName()] = [
                'name' => $configId->getFieldName(),
                'type' => $configId->getFieldType(),
                'label' => $entityConfig->get('label'),
            ];
        }

        return $customFields;
    }

    /**
     * @param string $fieldType
     * @return bool
     */
    protected function isFieldTypeAllowed($fieldType)
    {
        return in_array($fieldType, $this->getAllowedFieldTypes(), true);
    }

    /**
     * @return array
     */
    protected function getAllowedFieldTypes()
    {
        return [
            'enum',
            'boolean',
        ];
    }
}