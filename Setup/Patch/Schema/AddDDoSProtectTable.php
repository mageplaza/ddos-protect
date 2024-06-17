<?php

namespace Mageplaza\DDoSProtect\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Zend_Db_Exception;

class AddDDoSProtectTable implements SchemaPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * Constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * {@inheritdoc}
     * @throws Zend_Db_Exception
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        if (!$this->moduleDataSetup->tableExists('mageplaza_ddos_protect')) {
            $table = $this->moduleDataSetup->getConnection()->newTable(
                $this->moduleDataSetup->getTable('mageplaza_ddos_protect')
            )
                ->addColumn(
                    'entity_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
                    'Entity ID'
                )
                ->addColumn(
                    'ip_address',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'IP Address'
                )
                ->addColumn(
                    'request_count',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false, 'default' => '0'],
                    'Request Count'
                )
                ->addColumn(
                    'last_request_time',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                    'Last Request Time'
                )
                ->setComment('Mageplaza DDoS Protect Table');
            $this->moduleDataSetup->getConnection()->createTable($table);
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->moduleDataSetup->getConnection()->dropTable($this->moduleDataSetup->getTable('mageplaza_ddos_protect'));
        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
}
