<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class filenotif extends eqLogic {
    /*     * *************************Attributs****************************** */

    public function checkNewFile() {
      $folder = $this->getConfiguration('foldertocheck');
      $oldMD5 = $this->getConfiguration('FolderMD5');
      if ($folder != '') {
          log::add('filenotif', 'debug', 'Lecture de : '.$folder);
          $listedfiles = scandir($folder);
          $newMD5 = md5(print_r($listedfiles, true));
          log::add('filenotif', 'debug', 'new MD5 : '.$newMD5);
          $this->setConfiguration('FolderMD5',$newMD5);
          $this->save();
      }

      if ($oldMD5 != $newMD5) {
        log::add('filenotif', 'debug', '=> New files detected');
        $this->checkAndUpdateCmd('flag_newfile', 1);
        sleep(10);
        $this->checkAndUpdateCmd('flag_newfile', 0);
      }else {
        log::add('filenotif', 'debug', '=> No change');
      }
    }

    public function checkNewFile2() {
      $folder = $this->getConfiguration('foldertocheck');

      if (substr($folder, -1) != "/") {
        $folder .= "/";
      }
      $oldMD5 = $this->getConfiguration('FolderMD5');
      $ext = $this->getConfiguration('extensiontocheck');

      if ($ext == '*' OR $ext == NULL ) {
        log::add('filenotif', 'debug', 'Lecture de : '.$folder. " En mode *");
        $listedfiles = glob($folder.'*');
      } else {
        log::add('filenotif', 'debug', 'Lecture de : '.$folder. " En mode BRACE :".$ext);
        $listedfiles = glob($folder."*.{".$ext."}", GLOB_BRACE);
      }
      $newMD5 = md5(print_r($listedfiles, true));
      $newCount = count($listedfiles);
      log::add('filenotif', 'debug', 'Glob retourne : '.print_r($listedfiles, true));
      log::add('filenotif', 'debug', 'Nombres de fichiers : '.$newCount);
      $this->setConfiguration('FolderMD5',$newMD5);
      $this->save();

      log::add('filenotif', 'debug', 'MD5(new)= '.$newMD5);
      log::add('filenotif', 'debug', 'MD5(old)= '.$oldMD5);
      if ($oldMD5 != $newMD5) {
        log::add('filenotif', 'debug', '=> Changement détecté !');
        $oldCount = $this->getConfiguration('FilesCount', 0);
        $deltaCount = $newCount - $oldCount;
        log::add('filenotif', 'debug', 'Comptage OLD='.$oldCount." NEW=".$newCount);
          if ($deltaCount < 0 AND $this->getConfiguration('notifydel') == 0) {
            // NO Notif
          } else {
            $this->checkAndUpdateCmd('info_filecount', $deltaCount);
            $this->setConfiguration('FilesCount', $newCount);
            $this->save();
            $this->checkAndUpdateCmd('flag_newfile', 1);
            sleep(10);
            $this->checkAndUpdateCmd('flag_newfile', 0);
          }
      }else {
        log::add('filenotif', 'debug', '=> RAS');
      }
    }

  /*
   * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
   * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
	public static $_widgetPossibility = array();
   */

    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {
      }
     */


     // Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
      public static function cron() {
        $eqLogics = ($_eqlogic_id !== null) ? array(eqLogic::byId($_eqlogic_id)) : eqLogic::byType('filenotif', true);
    		foreach ($eqLogics as $filenotif) {
    			$autorefresh = $filenotif->getConfiguration('autorefresh','*/5 * * * *');
    			if ($autorefresh != '') {
    				try {
    					$c = new Cron\CronExpression(checkAndFixCron($autorefresh), new Cron\FieldFactory);
    					if ($c->isDue()) {
                log::add('filenotif', 'debug', 'Execution du process de vérification pour : '.$filenotif->getHumanName());
    						$filenotif->checkNewFile2();
    					}
    				} catch (Exception $exc) {
    					log::add('filenotif', 'error', __('Expression cron non valide pour ', __FILE__) . $filenotif->getHumanName() . ' : ' . $autorefresh);
    				}
    			}
    		}
      }


    /*
     * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
      public static function cron10() {
      }
     */

    /*
     * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
      public static function cron15() {
      }
     */

    /*
     * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
      public static function cron30() {
      }
     */

    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {
      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {
      }
     */



    /*     * *********************Méthodes d'instance************************* */

 // Fonction exécutée automatiquement avant la création de l'équipement
    public function preInsert() {

    }

 // Fonction exécutée automatiquement après la création de l'équipement
    public function postInsert() {

      $filenotifCmd = new filenotifCmd();
      $filenotifCmd->setName(__('Rafraichir', __FILE__));
      $filenotifCmd->setEqLogic_id($this->id);
      $filenotifCmd->setType('action');
      $filenotifCmd->setSubType('other');
      $filenotifCmd->setLogicalId('refresh');
      $filenotifCmd->setOrder(1);
      $filenotifCmd->save();

      $filenotifCmd = new filenotifCmd();
      $filenotifCmd->setName(__('Changement détecté', __FILE__));
      $filenotifCmd->setEqLogic_id($this->id);
      $filenotifCmd->setType('info');
      $filenotifCmd->setSubType('binary');
      $filenotifCmd->setIsHistorized(0);
      $filenotifCmd->setLogicalId('flag_newfile');
      $filenotifCmd->setOrder(2);
      $filenotifCmd->save();

      $filenotifCmd = new filenotifCmd();
      $filenotifCmd->setName(__('Quantité fichier', __FILE__));
      $filenotifCmd->setEqLogic_id($this->id);
      $filenotifCmd->setType('info');
      $filenotifCmd->setSubType('numeric');
      $filenotifCmd->setIsHistorized(0);
      $filenotifCmd->setLogicalId('info_filecount');
      $filenotifCmd->setOrder(3);
      $filenotifCmd->save();
    }

 // Fonction exécutée automatiquement avant la mise à jour de l'équipement
    public function preUpdate() {

    }

 // Fonction exécutée automatiquement après la mise à jour de l'équipement
    public function postUpdate() {

    }

 // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
    public function preSave() {

    }

 // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
    public function postSave() {

    }

 // Fonction exécutée automatiquement avant la suppression de l'équipement
    public function preRemove() {

    }

 // Fonction exécutée automatiquement après la suppression de l'équipement
    public function postRemove() {

    }

    /*
     * Non obligatoire : permet de modifier l'affichage du widget (également utilisable par les commandes)
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire : permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire : permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class filenotifCmd extends cmd {
    /*     * *************************Attributs****************************** */

    /*
      public static $_widgetPossibility = array();
    */

    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

  // Exécution d'une commande
     public function execute($_options = array()) {
       $eqLogic = $this->getEqlogic();
       $eqLogic->checkNewFile2($result);
     }

    /*     * **********************Getteur Setteur*************************** */
}
