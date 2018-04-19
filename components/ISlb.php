<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/11/18
 * Time: 8:23 PM
 */

namespace app\components;

interface ISlb
{
    const SLB_TYPE_ALIYUN = "aliyun";
    const SLB_TYPE_MICROSOFT = "microft";
    const SLB_TYPE = "slb_type";

    /**
     * get ecs ip list by slb config
     **/
    public function getEcsIpList($config = []);

    /**
     * set backend server weight
     **/
    public function setBackendServerWeight($config = [], $ip, $weight);

    /**
     * get weight by ips
     * @param array $config
     * @param array $ips
     * @return mixed
     */
    public function getWeightByIps($config = [], $ips = []);
}