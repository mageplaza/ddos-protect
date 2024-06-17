<?php

namespace Mageplaza\DDoSProtect\Plugin;

use Closure;
use DateTime;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class FrontControllerPlugin
 *  Mageplaza\DDoSProtect\Plugin
 */
class FrontControllerPlugin
{
    const MAX_REQUESTS       = 'ddos_protect/general/max_requests'; // Maximum number of requests allowed
    const TIME_WINDOW        = 'ddos_protect/general/time_window'; // Time window in seconds
    const XML_PATH_WHITELIST = 'ddos_protect/general/whitelist';
    const ENABLE             = 'ddos_protect/general/enable';

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
     * @param ResultFactory $resultFactory
     * @param ResourceConnection $resource
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ResultFactory $resultFactory,
        ResourceConnection $resource,
        ScopeConfigInterface $scopeConfig
    ) {
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

        if ($request->getParam('is_protect_error_index') || !$this->isEnable()) {

            return $result;
        }

        if ($this->isDDoSAttack($request)) {
            die('Your request has been identified as potentially harmful. Please try again later.');
        }

        return $result;
    }

    /**
     * Check if the request is part of a DDoS attack
     *
     * @param RequestInterface $request
     *
     * @return bool
     */
    protected function isDDoSAttack(RequestInterface $request)
    {
        $ipAddress = $request->getClientIp();
        // Check if the IP is in the whitelist
        $whitelist = $this->getWhitelist();
        if (in_array($ipAddress, $whitelist)) {
            return false;
        }

        $connection = $this->resource->getConnection();
        $tableName  = $this->resource->getTableName('mageplaza_ddos_protect');
        // Calculate the time 15 minutes ago
        $currentTime = time();
        $startTime   = $currentTime - 900; // 900 seconds = 15 minutes

        $select = $connection->select()
            ->from($tableName)
            ->where('ip_address = ?', $ipAddress)
            ->where('last_request_time >= ?', date('Y-m-d H:i:s', $startTime));

        $result = $connection->fetchRow($select);

        if ($result) {
            $requestCount    = (int) $result['request_count'];
            $lastRequestTime = strtotime($result['last_request_time']);
            $writer          = new \Zend_Log_Writer_Stream(BP . '/var/log/Neil.log');
            $logger          = new \Zend_Log();
            $logger->addWriter($writer);
            $logger->info(json_encode($result));

            if ((time() - $lastRequestTime) <= $this->getTimeWindow()) {
                if ($requestCount >= $this->getMaxRequests()) {
                    return true;
                } else {
                    $connection->update(
                        $tableName,
                        ['request_count' => $requestCount + 1],
                        ['entity_id = ?' => $result['entity_id']]
                    );
                }
            } else {
                // Reset Count Request after more time have no request 60 second | self::TIME_WINDOW
                $connection->update(
                    $tableName,
                    ['request_count' => 1, 'last_request_time' => (new DateTime())->format('Y-m-d H:i:s')],
                    ['entity_id = ?' => $result['entity_id']]
                );
            }
        } else {
            $connection->insert(
                $tableName,
                [
                    'ip_address'        => $ipAddress,
                    'request_count'     => 1,
                    'last_request_time' => (new DateTime())->format('Y-m-d H:i:s')
                ]
            );
        }

        return false;
    }

    /**
     * Get the IP whitelist from configuration
     *
     * @return array
     */
    protected function getWhitelist()
    {
        $whitelist = $this->scopeConfig->getValue(self::XML_PATH_WHITELIST);

        return $whitelist ? array_map('trim', explode(',', $whitelist)) : [];
    }

    /**
     * @return int
     */
    protected function getTimeWindow()
    {
        return (int) $this->scopeConfig->getValue(self::TIME_WINDOW);
    }

    /**
     * @return int
     */
    protected function getMaxRequests()
    {
        return (int) $this->scopeConfig->getValue(self::MAX_REQUESTS);
    }

    /**
     * @return int
     */
    protected function isEnable()
    {
        return (int) $this->scopeConfig->getValue(self::ENABLE);
    }
}
