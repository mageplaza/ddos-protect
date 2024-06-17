<?php

namespace Mageplaza\DDoSProtect\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Request extends AbstractDb
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('mageplaza_ddos_protect', 'entity_id');
    }
}
