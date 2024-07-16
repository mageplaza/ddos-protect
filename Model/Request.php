<?php

namespace Mageplaza\DDoSProtect\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class Request
 * @package Mageplaza\DDoSProtect\Model
 */
class Request extends AbstractModel
{
    const CLIENT_IP_CACHE_KEY = 'client_ip';
    const IP_ATTACK = 'ip_attack';
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Mageplaza\DDoSProtect\Model\ResourceModel\Request');
    }
}
