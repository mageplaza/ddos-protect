<?php

namespace Mageplaza\DDoSProtect\Plugin;

use Closure;
use DateTime;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Mageplaza\DDoSProtect\Model\Request;

/**
 * Class FrontControllerPlugin
 *  Mageplaza\DDoSProtect\Plugin
 */
class FrontControllerPlugin
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Constructor
     *
     * @param CacheInterface $cache
     * @param ResultFactory $resultFactory
     * @param ResourceConnection $resource
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CacheInterface $cache,
        ResultFactory $resultFactory,
        ResourceConnection $resource,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->cache         = $cache;
        $this->resultFactory = $resultFactory;
        $this->resource      = $resource;
        $this->scopeConfig   = $scopeConfig;
    }

    /**
     * Around dispatch plugin
     *
     * @param FrontControllerInterface $subject
     * @param Closure $proceed
     * @param RequestInterface $request
     *
     * @return ActionInterface|ResponseInterface|Redirect
     */
    public function aroundDispatch(FrontControllerInterface $subject, Closure $proceed, RequestInterface $request)
    {
        $result = $proceed($request);

        if ($request->getParam('is_protect_error_index')) {
            return $result;
        }
        /*add client ip to cache*/
        $this->appendDataToCache(Request::CLIENT_IP_CACHE_KEY, $request->getClientIp());
        $ipAttack = $this->cache->load(Request::IP_ATTACK);
        if ($ipAttack) {
            $ipAttack = json_decode($ipAttack, true);
            if (in_array($request->getClientIp(), $ipAttack)) {
                /** @var Redirect $resultRedirect */
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setPath(
                    'protect/error',
                    ['is_protect_error_index' => true]
                ); // Redirect to a custom error page

                return $resultRedirect;
            }
        }

        return $result;
    }

    /**
     * Save data to cache
     *
     * @param string $cacheKey
     * @param array $data
     * @param int $lifetime
     * @return void
     */
    protected function saveDataToCache($cacheKey, array $data, $lifetime = 3600)
    {
        $serializedData = json_encode($data);
        $this->cache->save($serializedData, $cacheKey, [], $lifetime);
    }

    /**
     * Get data from cache
     *
     * @param string $cacheKey
     * @return array|null
     */
    protected function getDataFromCache($cacheKey)
    {
        $cachedData = $this->cache->load($cacheKey);

        if ($cachedData) {
            return json_decode($cachedData, true);
        } else {
            return null;
        }
    }

    /**
     * Append data to existing cache key
     *
     * @param string $cacheKey
     * @param string $clientIP
     * @param int $lifetime
     * @return void
     */
    protected function appendDataToCache($cacheKey, $clientIP, $lifetime = 3600)
    {
        $existingData = $this->getDataFromCache($cacheKey) ?: [];
        $existingData[] = $clientIP;
        $this->saveDataToCache($cacheKey, $existingData, $lifetime);
    }
}
