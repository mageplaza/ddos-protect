<?php

namespace Mageplaza\DDoSProtect\Model;

use Magento\Framework\Model\AbstractModel;

class Request extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Mageplaza\DDoSProtect\Model\ResourceModel\Request');
    }
}
