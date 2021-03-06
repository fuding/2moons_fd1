<?php

/**
 *  2Moons
 *  Copyright (C) 2011  Slaver
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package 2Moons
 * @author Slaver <slaver7@gmail.com>
 * @copyright 2009 Lucky <lucky@xgproyect.net> (XGProyecto)
 * @copyright 2011 Slaver <slaver7@gmail.com> (Fork/2Moons)
 * @license http://www.gnu.org/licenses/gpl.html GNU GPLv3 License
 * @version 1.6.1 (2011-11-19)
 * @info $Id$
 * @link http://code.google.com/p/2moons/
 */

require('includes/libs/Smarty/Smarty.class.php');
		
class Template
{
	protected $window	= 'full';
	public $jsscript	= array();
	public $script		= array();


	/**
	 * reference of the Smarty object
	 * @var Smarty
	 */
	private $smarty;

	function __construct()
	{
		$this->smarty	= new Smarty();
		$this->smartySettings();
	}

	public function getSmartyObj()
	{
		return $this->smarty;
	}

	private function smartySettings()
	{
        global $THEME;
		$this->smarty->caching 					= true;
		$this->smarty->merge_compiled_includes	= true;
		$this->smarty->compile_check			= true; #Set false for production!
		$this->smarty->php_handling				= Smarty::PHP_REMOVE;

		$this->smarty->setPluginsDir(array(
			'includes/libs/Smarty/plugins/',
			'includes/classes/smarty-plugins/',
		));

		$baseCachePath	= is_writable(CACHE_PATH.'templates/') ? CACHE_PATH.'templates/' : $this->getTempPath();

		$this->smarty->setCompileDir($baseCachePath.'compile/');
		$this->smarty->setCacheDir($baseCachePath.'cache/');

		$this->smarty->setTemplateDir(array(
            $THEME->getTemplatePath().strtolower(MODE),
            TEMPLATE_PATH.strtolower(MODE)
        ));
	}

	private function getTempPath()
	{
		$this->smarty->force_compile 		= true;
		require_once 'includes/libs/wcf/BasicFileUtil.class.php';
		return BasicFileUtil::getTempFolder();
	}
		
	public function assign_vars($var, $nocache = true) 
	{
		$this->smarty->assign($var, NULL, $nocache);
	}

	public function loadscript($script)
	{
		$this->jsscript[]			= substr($script, 0, -3);
	}

	public function execscript($script)
	{
		$this->script[]				= $script;
	}
	
	private function adm_main()
	{
		global $LNG, $USER;
		
		$dateTimeServer		= new DateTime("now");
		if(isset($USER['timezone'])) {
			try {
				$dateTimeUser	= new DateTime("now", new DateTimeZone($USER['timezone']));
			} catch (Exception $e) {
				$dateTimeUser	= $dateTimeServer;
			}
		} else {
			$dateTimeUser	= $dateTimeServer;
		}

		$config	= Config::get();

		$this->assign_vars(array(
			'scripts'			=> $this->script,
			'title'				=> $config->game_name.' - '.$LNG['adm_cp_title'],
			'fcm_info'			=> $LNG['fcm_info'],
            'lang'    			=> $LNG->getLanguage(),
			'REV'				=> substr($config->VERSION, -4),
			'date'				=> explode("|", date('Y\|n\|j\|G\|i\|s\|Z', TIMESTAMP)),
			'Offset'			=> $dateTimeUser->getOffset() - $dateTimeServer->getOffset(),
			'VERSION'			=> $config->VERSION,
			'dpath'				=> 'styles/theme/gow/',
			'bodyclass'			=> 'full'
		));
	}
	
	public function show($file)
	{		
		global $LNG;

		if(MODE === 'ADMIN') {
			$this->adm_main();
		}

		$this->assign_vars(array(
			'scripts'		=> $this->jsscript,
			'execscript'	=> implode("\n", $this->script),
		));

		$this->assign_vars(array(
			'LNG'			=> $LNG,
		), false);

		
		$this->display($file);
	}

	public function display($file)
	{
		global $LNG, $THEME;
		$this->smarty->compile_id	= $LNG->getLanguage().'_'.$THEME->getThemeName();
		$this->smarty->display($file);
	}
	
	public function gotoside($dest, $time = 3)
	{
		$this->assign_vars(array(
			'gotoinsec'	=> $time,
			'goto'		=> $dest,
		));
	}
	
	public function message($mes, $dest = false, $time = 3, $Fatal = false)
	{
		global $LNG, $THEME;
	
		$this->assign_vars(array(
			'mes'		=> $mes,
			'fcm_info'	=> $LNG['fcm_info'],
			'Fatal'		=> $Fatal,
            'dpath'		=> $THEME->getTheme(),
		));
		
		$this->gotoside($dest, $time);
		$this->show('error_message_body.tpl');
	}
	
	public static function printMessage($Message, $fullSide = true, $redirect = NULL) {
		$template	= new self;
		if(!isset($redirect)) {
			$redirect	= array(false, 0);
		}
		
		$template->message($Message, $redirect[0], $redirect[1], !$fullSide);
		exit;
	}
}
