<?php

/**
 *  2Moons
 *  Copyright (C) 2012 Jan Kröpke
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
 * @author Jan Kröpke <info@2moons.cc>
 * @copyright 2012 Jan Kröpke <info@2moons.cc>
 * @license http://www.gnu.org/licenses/gpl.html GNU GPLv3 License
 * @version 1.8.0 (2013-03-18)
 * @info $Id$
 * @link http://2moons.cc/
 */

class MissionCaseFoundDM extends AbstractMission
{
	const CHANCE = 30; 
	const CHANCE_SHIP = 0.25; 
	const MIN_FOUND = 423; 
	const MAX_FOUND = 1278; 
	const MAX_CHANCE = 50;

	public function arrivalEndTargetEvent()
	{
		$this->setNextState(FLEET_HOLD);
	}

	public function endStayTimeEvent()
	{
		$LNG	= $this->getLanguage(NULL, $this->fleetData['fleet_owner']);

		$chance	= min(self::MAX_CHANCE, (self::CHANCE + array_sum($this->fleetData['elements'][Vars::CLASS_FLEET]) * self::CHANCE_SHIP));
		if(mt_rand(0, 100) <= $chance)
		{
			$foundDarkMatter 	= mt_rand(self::MIN_FOUND, self::MAX_FOUND);

			if(!isset($this->fleetData['elements'][Vars::CLASS_RESOURCE][921]))
			{
				$this->fleetData['elements'][Vars::CLASS_RESOURCE][921] = 0;
			}

			$this->fleetData['elements'][Vars::CLASS_RESOURCE][921] += $foundDarkMatter;

			$playerMessage 	= $LNG['sys_expe_found_dm_'.mt_rand(1, 3).'_'.mt_rand(1, 2).''];
		} else {
			$playerMessage 	= $LNG['sys_expe_nothing_'.mt_rand(1, 9)];
		}
		$this->setNextState(FLEET_RETURN);

		PlayerUtil::sendMessage($this->fleetData['fleet_owner'], 0, $LNG['sys_mess_tower'], 15,
			$LNG['sys_expe_report'], $playerMessage, $this->fleetData['fleet_end_stay'], NULL, 1, $this->fleetData['fleet_universe']);
	}
	
	public function arrivalStartTargetEvent()
	{
		$sql			= 'SELECT lang FROM %%USERS%% WHERE id = :userId;';
		$userLanguage	= Database::get()->selectSingle($sql, array(
			':userId'	=> $this->fleetData['fleet_owner'],
		), 'lang');

		$LNG			= $this->getLanguage($userLanguage);

		if($this->fleetData['elements'][Vars::CLASS_RESOURCE][921] > 0)
		{
			$message	= sprintf($LNG['sys_expe_back_home_with_dm'],
				$LNG['tech'][921],
				pretty_number($this->fleetData['fleet_resource_darkmatter']),
				$LNG['tech'][921]
			);

			$fleetData	= array();
		}
		else
		{
			$message	= $LNG['sys_expe_back_home_without_dm'];
			$fleetData	= $this->fleetData['elements'][Vars::CLASS_FLEET];
		}

		PlayerUtil::sendMessage($this->fleetData['fleet_owner'], 0, $LNG['sys_mess_tower'], 4, $LNG['sys_mess_fleetback'],
			$message, $this->fleetData['fleet_end_time'], NULL, 1, $this->fleetData['fleet_universe']);

		$this->arrivalTo($this->fleetData['fleet_start_id'], $fleetData, $this->fleetData['elements'][Vars::CLASS_RESOURCE]);
	}
}