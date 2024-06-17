<?php

namespace Mageplaza\DDoSProtect\Model\ResourceModel\Request;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mageplaza\DDoSProtect\Model\Request as Model;
use Mageplaza\DDoSProtect\Model\ResourceModel\Request as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
