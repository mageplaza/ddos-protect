<?php

namespace Mageplaza\DDoSProtect\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class Request
 * Mageplaza\DDoSProtect\Model
 */
class Request extends AbstractModel
{
    const CLIENT_IP_CACHE_KEY = 'mp_ddos_protect_client_ip';
    const IP_ATTACK = 'mp_ddos_protect_ip_attack';
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Request::class);
    }
}
