<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_DDoSProtect
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\DDoSProtect\Cron;

use Mageplaza\DDoSProtect\Helper\DDos;

/**
 * Class CheckIP
 * @package Mageplaza\DDoSProtect\Cron
 */
class SaveAttackIP
{
    /**
     * @var DDos
     */
    private $helperDDoS;

    /**
     * CheckIP constructor.
     *
     * @param DDos $DDosHelper
     */
    public function __construct(
        DDos $DDosHelper
    ) {
        $this->helperDDoS = $DDosHelper;
    }

    /**
     * @return $this
     */
    public function execute()
    {
        $this->helperDDoS->saveIpAttackToDB();

        return $this;
    }
}
