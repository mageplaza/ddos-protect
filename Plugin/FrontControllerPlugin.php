<?php

namespace Mageplaza\DDoSProtect\Plugin;

use Closure;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Mageplaza\DDoSProtect\Helper\DDos;

/**
 * Class FrontControllerPlugin
 *  Mageplaza\DDoSProtect\Plugin
 */
class FrontControllerPlugin
{

    /**
     * @var DDos
     */
    protected $helperData;

    /**
     * FrontControllerPlugin constructor.
     *
     * @param DDos $helperData
     */
    public function __construct(
        DDos $helperData
    ) {
        $this->helperData = $helperData;
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

        /*check request path info config*/
        if ($this->onlyProtectSpecialRequest($request)) {
            return $result;
        }

        /*add client ip to cache*/
        $ipClient  = $request->getClientIp();
        $ipAttacks = $this->helperData->loadIpAttackCache();

        if (!empty($ipAttacks) && in_array($ipClient, array_keys($ipAttacks))) {
            die('You have baned');
        }
        $this->helperData->handleClientIp($ipClient);

        return $result;
    }

    /**
     * @param $request
     *
     * @return bool
     */
    public function onlyProtectSpecialRequest($request)
    {
        $limitRequest = $this->helperData->getPath();
        if (!$limitRequest) {
            return false;
        }
        $pathInfo = explode('/', $request->getPathInfo());
        $paths    = explode("\n", str_replace("\r", '', $limitRequest));
        foreach ($paths as $path) {
            $match   = 0;
            $urlKeys = explode('/', $path);
            if (count($urlKeys) <= 2 && str_contains($request->getPathInfo(), $path)) {
                return true;
            }
            foreach ($urlKeys as $index => $urlKeyValue) {
                if ($urlKeyValue !== $pathInfo[$index]) {
                    $match = 0;
                    continue;
                }
                $match++;
            }
            if ($match) {
                return true;
            }
        }

        return false;
    }
}
