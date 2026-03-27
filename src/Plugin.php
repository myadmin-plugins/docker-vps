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
    public static $name = 'DOCKER VPS';
    public static $description = 'Allows selling of DOCKER VPS Types.  DOCKER (for Kernel-based Virtual Machine) is a full virtualization solution for Linux on x86 hardware containing virtualization extensions (Intel VT or AMD-V). It consists of a loadable kernel module, docker.ko, that provides the core virtualization infrastructure and a processor specific module, docker-intel.ko or docker-amd.ko.  Using DOCKER, one can run multiple virtual machines running unmodified Linux or Windows images. Each virtual machine has private virtualized hardware: a network card, disk, graphics adapter, etc.  More info at https://www.linux-docker.org/';
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
        if (in_array($event['type'], [get_service_define('DOCKER_LINUX'), get_service_define('DOCKER_WINDOWS'), get_service_define('CLOUD_DOCKER_LINUX'), get_service_define('CLOUD_DOCKER_WINDOWS'), get_service_define('DOCKERV2'), get_service_define('DOCKERV2_WINDOWS'), get_service_define('DOCKERV2_STORAGE')])) {
            myadmin_log(self::$module, 'info', self::$name.' Activation', __LINE__, __FILE__, self::$module, $serviceClass->getId(), true, false, $serviceClass->getCustid());
            $event->stopPropagation();
        }
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getDeactivate(GenericEvent $event)
    {
        if (in_array($event['type'], [get_service_define('DOCKER_LINUX'), get_service_define('DOCKER_WINDOWS'), get_service_define('CLOUD_DOCKER_LINUX'), get_service_define('CLOUD_DOCKER_WINDOWS'), get_service_define('DOCKERV2'), get_service_define('DOCKERV2_WINDOWS'), get_service_define('DOCKERV2_STORAGE')])) {
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
        $settings->add_text_setting(self::$module, _('Slice Costs'), 'vps_slice_docker_l_cost', _('DOCKER Linux VPS Cost Per Slice'), _('DOCKER Linux VPS will cost this much for 1 slice.'), $settings->get_setting('VPS_SLICE_DOCKER_L_COST'));
        $settings->add_text_setting(self::$module, _('Slice Costs'), 'vps_slice_docker_w_cost', _('DOCKER Windows VPS Cost Per Slice'), _('DOCKER Windows VPS will cost this much for 1 slice.'), $settings->get_setting('VPS_SLICE_DOCKER_W_COST'));
        $settings->add_text_setting(self::$module, _('Slice Costs'), 'vps_slice_cloud_docker_l_cost', _('Cloud DOCKER Linux VPS Cost Per Slice'), _('Cloud DOCKER Linux VPS will cost this much for 1 slice.'), $settings->get_setting('VPS_SLICE_CLOUD_DOCKER_L_COST'));
        $settings->add_text_setting(self::$module, _('Slice Costs'), 'vps_slice_cloud_docker_w_cost', _('Cloud DOCKER Windows VPS Cost Per Slice'), _('Cloud DOCKER Windows VPS will cost this much for 1 slice.'), $settings->get_setting('VPS_SLICE_CLOUD_DOCKER_W_COST'));
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_docker_linux_server', _('DOCKERv2 Linux NJ Server'), NEW_VPS_DOCKER_LINUX_SERVER, 14, 1);
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_docker_storage_server', _('DOCKERv2 Storage NJ Server'), NEW_VPS_DOCKER_STORAGE_SERVER, 16, 1);
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_la_docker_win_server', _('DOCKERv2 LA Windows Server'), NEW_VPS_LA_DOCKER_WIN_SERVER, 15, 2);
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_la_docker_linux_server', _('DOCKERv2 LA Linux Server'), NEW_VPS_LA_DOCKER_LINUX_SERVER, 14, 2);
        $settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_ny_docker_win_server', _('DOCKER NY4 Windows Server'), NEW_VPS_NY_DOCKER_WIN_SERVER, 1, 3);
        //$settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_ny_docker_linux_server', _('DOCKER NY4 Linux Server'), NEW_VPS_NY_DOCKER_LINUX_SERVER, 2, 3);
        //$settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_cloud_docker_win_server', _('Cloud DOCKER Windows Server'), (defined('NEW_VPS_CLOUD_DOCKER_WIN_SERVER') ? NEW_VPS_CLOUD_DOCKER_WIN_SERVER : ''), 3);
        //$settings->add_select_master(_(self::$module), _('Default Servers'), self::$module, 'new_vps_cloud_docker_linux_server', _('Cloud DOCKER Linux Server'), (defined('NEW_VPS_CLOUD_DOCKER_LINUX_SERVER') ? NEW_VPS_CLOUD_DOCKER_LINUX_SERVER : ''), 3);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_linux', _('Out Of Stock DOCKER Linux Secaucus'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_LINUX'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_storage', _('Out Of Stock DOCKER Storage Secaucus'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_STORAGE'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_storage_la', _('Out Of Stock DOCKER Storage LA'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_STORAGE_LA'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_storage_tx', _('Out Of Stock DOCKER Storage TX'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_STORAGE_TX'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_win', _('Out Of Stock DOCKER Windows Secaucus'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_WIN'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_linux_la', _('Out Of Stock DOCKER Linux Los Angeles'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_LINUX_LA'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_win_la', _('Out Of Stock DOCKER Windows Los Angeles'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_WIN_LA'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_linux_tx', _('Out Of Stock DOCKER Linux TX'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_LINUX_TX'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_docker_win_tx', _('Out Of Stock DOCKER Windows TX'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_DOCKER_WIN_TX'), ['0', '1'], ['No', 'Yes']);
        $settings->add_dropdown_setting(self::$module, _('Out of Stock'), 'outofstock_clouddocker', _('Out Of Stock Cloud DOCKER'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_CLOUDDOCKER'), ['0', '1'], ['No', 'Yes']);
        $settings->setTarget('global');
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getQueue(GenericEvent $event)
    {
        if (in_array($event['type'], [get_service_define('DOCKER_LINUX'), get_service_define('DOCKER_WINDOWS'), get_service_define('CLOUD_DOCKER_LINUX'), get_service_define('CLOUD_DOCKER_WINDOWS'), get_service_define('DOCKERV2'), get_service_define('DOCKERV2_WINDOWS'), get_service_define('DOCKERV2_STORAGE')])) {
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
