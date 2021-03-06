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

class ShowFleetStep1Page extends AbstractGamePage
{
	public static $requireModule = MODULE_FLEET_TABLE;

	function __construct() 
	{
		parent::__construct();
	}
	
	public function show()
	{
		global $USER, $PLANET, $LNG;
		
		$targetGalaxy 	= HTTP::_GP('galaxy', (int) $PLANET['galaxy']);
		$targetSystem 	= HTTP::_GP('system', (int) $PLANET['system']);
		$targetPlanet	= HTTP::_GP('planet', (int) $PLANET['planet']);
		$targetType 	= HTTP::_GP('type', (int) $PLANET['planet_type']);
		
		$targetMission	= HTTP::_GP('target_mission', 0);

        $selectedShips	= HTTP::_GP('ship', array());

		$fleetData		= array();

		foreach (Vars::getElements(Vars::CLASS_FLEET) as $elementId => $elementObj)
		{
            if(!isset($selectedShips[$elementId]) || $selectedShips[$elementId] <= 0) continue;

			$amount		 				= round($selectedShips[$elementId], 0);

            if(FleetUtil::GetFleetMaxSpeed($elementId, $USER) == 0) continue;

            $fleetData[$elementId]      = $amount;
		}

        $fleetRoom  = FleetUtil::GetFleetRoom($fleetData);
		$fleetRoom	+= PlayerUtil::getBonusValue($fleetRoom, 'ShipStorage', $USER);
		
		if (empty($fleetData))
        {
            $this->redirectTo('game.php?page=fleetTable');
        }

		$missionData	= array(
			'fleetRoom'	=> floattostring($fleetRoom),
			'data'		=> $this->_calculateCost($fleetData, array(
				'galaxy' 	=> $PLANET['galaxy'],
				'system' 	=> $PLANET['system'],
				'planet' 	=> $PLANET['planet'],
				'type' 		=> $PLANET['planet_type']
			), 100)
		);

        $session    = Session::load();
        $token		= getRandomString();

        $session->{"fleet_$token"} = array(
			'userId'		=> $USER['id'],
			'planetId'		=> $PLANET['id'],
			'time'			=> TIMESTAMP,
			'fleetData'		=> $fleetData,
			'fleetRoom'	    => $fleetRoom,
		);

		$shortcutList	= $this->getUserShortcuts();
		$shortcutAmount	= count($shortcutList);

		$this->assign(array(
			'token'				=> $token,
			'mission'			=> $targetMission,
			'shortcutList'		=> $shortcutList,
			'shortcutMax'		=> $shortcutAmount,
			'colonyList' 		=> $this->getColonyList(),
			'fleetGroupList' 	=> $this->getAvailableFleetGroups(),
			'target' 			=> array('galaxy' => $targetGalaxy, 'system' => $targetSystem, 'planet' => $targetPlanet, 'type' => $targetType),
			'speedSelect'		=> FleetUtil::$allowedSpeed,
			'typeSelect'   		=> ArrayUtil::filterArrayWithKeys($LNG['type_planet'], array(1, 2, 3)),
			'missionData'		=> $missionData,
		));
		
		$this->display('page.fleetStep1.default.tpl');
	}
	
	public function saveShortcuts()
	{
		global $USER, $LNG;
		
		if(!isset($_REQUEST['shortcut'])) {
			$this->sendJSON($LNG['fl_shortcut_saved']);
		}

        $db = Database::get();

		$ShortcutData	= $_REQUEST['shortcut'];
		$ShortcutUser	= $this->getUserShortcuts();

		foreach($ShortcutData as $ID => $planetData) {
			if(!isset($ShortcutUser[$ID]))
			{
				if(empty($planetData['name']) || empty($planetData['galaxy']) || empty($planetData['system']) || empty($planetData['planet'])) {
					continue;
				}

                $sql = "INSERT INTO %%SHORTCUTS%% SET ownerID = :userId, name = :name, galaxy = :galaxy, system = :system, planet = :planet, type = :type;";
                $db->insert($sql, array(
                    ':userId'   => $USER['id'],
                    ':name'     => $planetData['name'],
                    ':galaxy'   => $planetData['galaxy'],
                    ':system'   => $planetData['system'],
                    ':planet'   => $planetData['planet'],
                    ':type'     => $planetData['type']
                ));
			}
			elseif(empty($planetData['name']))
			{
				$sql = "DELETE FROM %%SHORTCUTS%% WHERE shortcutID = :shortcutID AND ownerID = :userId;";
                $db->delete($sql, array(
                    ':shortcutID'   => $ID,
                    ':userId'       => $USER['id']
                ));
            }
			else
			{
				$planetData['ownerID']		= $USER['id'];
				$planetData['shortcutID']	= $ID;
				if($planetData != $ShortcutUser[$ID])
				{
                    $sql = "UPDATE %%SHORTCUTS%% SET name = :name, galaxy = :galaxy, system = :system, planet = :planet, type = :type WHERE shortcutID = :shortcutID AND ownerID = :userId;";
                    $db->update($sql, array(
                        ':userId'  		=> $USER['id'],
                        ':name'     	=> $planetData['name'],
                        ':galaxy'   	=> $planetData['galaxy'],
                        ':system'   	=> $planetData['system'],
                        ':planet'   	=> $planetData['planet'],
                        ':type'     	=> $planetData['type'],
                        ':shortcutID'   => $ID
                    ));
                }
			}
		}
		
		$this->sendJSON($LNG['fl_shortcut_saved']);
	}
	
	private function getColonyList()
	{
		global $PLANET, $USER;
		
		$ColonyList	= array();
		
		foreach($USER['PLANETS'] as $planet)
		{
			if ($PLANET['id'] == $planet['id'])
				continue;
			
			$ColonyList[] = array(
				'name'		=> $planet['name'],
				'galaxy'	=> $planet['galaxy'],
				'system'	=> $planet['system'],
				'planet'	=> $planet['planet'],
				'type'		=> $planet['planet_type'],
			);	
		}
			
		return $ColonyList;
	}
	
	private function getUserShortcuts()
	{
		global $USER;
		
		if (!isModulAvalible(MODULE_SHORTCUTS))
			return array();

        $db = Database::get();

        $sql = "SELECT * FROM %%SHORTCUTS%% WHERE ownerID = :userId;";
        $ShortcutResult = $db->select($sql, array(
            ':userId'   => $USER['id']
        ));

        $ShortcutList	= array();

		foreach($ShortcutResult as $ShortcutRow) {
			$ShortcutList[$ShortcutRow['shortcutID']] = $ShortcutRow;
		}
		
		return $ShortcutList;
	}
	
	private function getAvailableFleetGroups()
	{
		global $USER;
		
		$db = Database::get();

        $sql = "SELECT acs.id, acs.name, planet.galaxy, planet.system, planet.planet, planet.planet_type as type
		FROM %%USERS_ACS%%
		INNER JOIN %%AKS%% acs ON acsID = acs.id
		INNER JOIN %%PLANETS%% planet ON planet.id = acs.target
		WHERE userID = :userId AND :maxFleets > (SELECT COUNT(*) FROM %%FLEETS%% WHERE fleet_group = acsID);";

		$fleetGroupList = $db->select($sql, array(
            ':userId'       => $USER['id'],
            ':maxFleets'    => Config::get()->max_fleets_per_acs,
        ));
		
		return $fleetGroupList;
	}
	
	function checkTarget()
	{
		global $PLANET, $LNG, $USER;

		$targetGalaxy 		= HTTP::_GP('galaxy', 0);
		$targetSystem 		= HTTP::_GP('system', 0);
		$targetPlanet		= HTTP::_GP('planet', 0);
		$targetType			= HTTP::_GP('type', 1);
	
		if($targetGalaxy == $PLANET['galaxy'] && $targetSystem == $PLANET['system'] && $targetPlanet == $PLANET['planet'] && $targetType == $PLANET['planet_type'])
		{
			$this->sendJSON($LNG['fl_error_same_planet']);
		}

		// If target is expedition
		if ($targetPlanet != Config::get()->max_planets + 1)
		{
			$db = Database::get();
            $sql = "SELECT u.id, u.urlaubs_modus, u.user_lastip, u.authattack,
            	p.destroyed, p.der_metal, p.der_crystal, p.destroyed
                FROM %%USERS%% as u, %%PLANETS%% as p WHERE
                p.universe = :universe AND
                p.galaxy = :targetGalaxy AND
                p.system = :targetSystem AND
                p.planet = :targetPlanet  AND
                p.planet_type = :targetType AND
                u.id = p.id_owner;";

			$planetData = $db->selectSingle($sql, array(
                ':universe'     => Universe::current(),
                ':targetGalaxy' => $targetGalaxy,
                ':targetSystem' => $targetSystem,
                ':targetPlanet' => $targetPlanet,
                ':targetType' 	=> $targetType == 2 ? 1 : $targetType,
            ));

            if ($targetType == MOON && !isset($planetData))
			{
				$this->sendJSON($LNG['fl_error_no_moon']);
			}

			if ($targetType != DEBRIS && $planetData['urlaubs_modus'])
			{
				$this->sendJSON($LNG['fl_in_vacation_player']);
			}

			if ($planetData['id'] != $USER['id'] && Config::get()->adm_attack == 1 && $planetData['authattack'] > $USER['authlevel'])
			{
				$this->sendJSON($LNG['fl_admin_attack']);
			}

			if ($planetData['destroyed'] != 0)
			{
				$this->sendJSON($LNG['fl_error_not_avalible']);
			}

			if ($targetType == DEBRIS && $planetData['der_metal'] == 0 && $planetData['der_crystal'] == 0)
			{
				$this->sendJSON($LNG['fl_error_empty_derbis']);
			}

			$sql	= 'SELECT (
				(SELECT COUNT(*) FROM %%MULTI%% WHERE userId = :userId) +
				(SELECT COUNT(*) FROM %%MULTI%% WHERE userId = :dataID)
			) as count;';

			$multiCount	= $db->selectSingle($sql ,array(
				':userId' => $USER['id'],
				':dataID' => $planetData['id']
			), 'count');

			if(ENABLE_MULTIALERT && $USER['id'] != $planetData['id'] && $USER['authlevel'] != AUTH_ADM && $USER['user_lastip'] == $planetData['user_lastip'] && $multiCount != 2)
			{
				$this->sendJSON($LNG['fl_multi_alarm']);
			}
		}
		else
		{
			if ($USER[Vars::getElement(124)->name] == 0)
			{
				$this->sendJSON($LNG['fl_target_not_exists']);
			}
			
			$activeExpedition	= FleetUtil::getUsedSlots($USER['id'], 15, true);

			if ($activeExpedition >= FleetUtil::getExpeditionLimit($USER))
			{
				$this->sendJSON($LNG['fl_no_expedition_slot']);
			}
		}

		$this->sendJSON(false);
	}

	private function _calculateCost($fleetData, $planetPosition, $fleetSpeed)
	{
		global $USER, $PLANET;

		$distance   	= FleetUtil::GetTargetDistance(array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']), array_values($planetPosition));
		$fleetMaxSpeed 	= FleetUtil::GetFleetMaxSpeed($fleetData, $USER);
		$SpeedFactor    = FleetUtil::GetGameSpeedFactor();
		$duration      	= FleetUtil::GetMissionDuration($fleetSpeed, $fleetMaxSpeed, $distance, $SpeedFactor, $USER);
		$consumption   	= FleetUtil::GetFleetConsumption($fleetData, $duration, $distance, $USER, $SpeedFactor);
		return array(
			'distance'			=> $distance,
			'fleetMaxSpeed'		=> $fleetMaxSpeed * $fleetSpeed / 100,
			'flyTime'			=> $duration,
			'consumption'		=> array_filter($consumption),
			'consumptionTotal'	=> array_sum($consumption)
		);
	}

	public function getMissionData()
	{
		$planetPosition	= HTTP::_GP('planetPosition', array());
		$fleetSpeed		= HTTP::_GP('fleetSpeed', 0);
		$token			= HTTP::_GP('token', '');
		$this->sendJSON($this->_calculateCost(Session::load()->{"fleet_$token"}['fleetData'], $planetPosition, $fleetSpeed));
	}
}