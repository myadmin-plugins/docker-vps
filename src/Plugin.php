<?php

namespace Detain\MyAdminDocker;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminDocker
 */
class Plugin
{
    public static $name = 'Docker VPS';
    public static $description = 'Allows selling of Docker VPS Types.  Docker (for Kernel-based Virtual Machine) is a full virtualization solution for Linux on x86 hardware containing virtualization extensions (Intel VT or AMD-V). It consists of a loadable kernel module, docker.ko, that provides the core virtualization infrastructure and a processor specific module, docker-intel.ko or docker-amd.ko.  Using Docker, one can run multiple virtual machines running unmodified Linux or Windows images. Each virtual machine has private virtualized hardware: a network card, disk, graphics adapter, etc.  More info at https://www.linux-docker.org/';
    public static $help = '';
    public static $module = 'vps';
    public static $type = 'service';

    /**
     * Plugin constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return array
     */
    public static function getHooks()
    {
        return [
            self::$module.'.settings' => [__CLASS__, 'getSettings'],
            //self::$module.'.activate' => [__CLASS__, 'getActivate'],
            self::$module.'.deactivate' => [__CLASS__, 'getDeactivate'],
            self::$module.'.queue' => [__CLASS__, 'getQueue'],
        ];
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getActivate(GenericEvent $event)
    {
        $serviceClass = $event->getSubject();
        if (in_array($event['type'], [get_service_define('DOCKER'), get_service_define('DOCKER_STORAGE')])) {
            myadmin_log(self::$module, 'info', self::$name.' Activation', __LINE__, __FILE__, self::$module, $serviceClass->getId(), true, false, $serviceClass->getCustid());
            $event->stopPropagation();
        }
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getDeactivate(GenericEvent $event)
    {
        if (in_array($event['type'], [get_service_define('DOCKER'), get_service_define('DOCKER_STORAGE')])) {
            $serviceClass = $event->getSubject();
            myadmin_log(self::$module, 'info', self::$name.' Deactivation', __LINE__, __FILE__, self::$module, $serviceClass->getId(), true, false, $serviceClass->getCustid());
            $GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
        }
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getSettings(GenericEvent $event)
    {
        /**
         * @var \MyAdmin\Settings $settings
         **/
        $settings = $event->getSubject();
        $settings->setTarget('module');
        $settings->add_text_setting(self::$module, _('Slice Costs'), 'vps_slice_docker_cost', _('Docker VPS Cost Per Slice'), _('Docker VPS will cost this much for 1 slice.'), $settings->get_setting('VPS_SLICE_DOCKER_COST'));
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_docker_server', _('Docker NJ Server'), NEW_VPS_DOCKER_SERVER, 14, 1);
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_la_docker_server', _('Docker LA Server'), NEW_VPS_LA_DOCKER_SERVER, 14, 2);
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_docker_storage_server', _('Docker Storage NJ Server'), NEW_VPS_DOCKER_STORAGE_SERVER, 16, 1);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker', _('Out Of Stock Docker Secaucus'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_la', _('Out Of Stock Docker Los Angeles'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_LA'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_tx', _('Out Of Stock Docker TX'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_TX'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_storage', _('Out Of Stock Docker Storage Secaucus'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_STORAGE'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_storage_la', _('Out Of Stock Docker Storage LA'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_STORAGE_LA'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_storage_tx', _('Out Of Stock Docker Storage TX'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_STORAGE_TX'), ['0', '1'], ['No', 'Yes']);
        $settings->setTarget('global');
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getQueue(GenericEvent $event)
    {
        if (in_array($event['type'], [get_service_define('DOCKER'), get_service_define('DOCKER_STORAGE')])) {
            $serviceInfo = $event->getSubject();
            $settings = get_module_settings(self::$module);
            $server_info = $serviceInfo['server_info'];
            if (!file_exists(__DIR__.'/../templates/'.$serviceInfo['action'].'.sh.tpl')) {
                myadmin_log(self::$module, 'error', 'Call '.$serviceInfo['action'].' for VPS '.$serviceInfo['vps_hostname'].'(#'.$serviceInfo['vps_id'].'/'.$serviceInfo['vps_vzid'].') Does not Exist for '.self::$name, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id'], true, false, $serviceInfo[$settings['PREFIX'].'_custid']);
            } else {
                $smarty = new \TFSmarty();
                $smarty->assign($serviceInfo);
                //$smarty->assign('vps_vzid', isset($vps['module']) && $vps['module'] == 'quickservers' ? 'qs'.$vps['vps_vzid'] : (is_numeric($vps['vps_vzid']) ? (in_array($event['type'], [get_service_define('DOCKER_WINDOWS'), get_service_define('CLOUD_DOCKER_WINDOWS')]) ? 'windows'.$vps['vps_vzid'] : 'linux'.$vps['vps_vzid']) : $vps['vps_vzid']));
                $output = $smarty->fetch(__DIR__.'/../templates/'.$serviceInfo['action'].'.sh.tpl');
                myadmin_log(self::$module, 'info', 'Queue '.$server_info[$settings['PREFIX'].'_name'].' '.$output, __LINE__, __FILE__, self::$module, $serviceInfo['vps_id'], true, false, $serviceInfo['vps_custid']);
                $event['output'] = $event['output'].$output;
            }
            $event->stopPropagation();
        }
    }
}
