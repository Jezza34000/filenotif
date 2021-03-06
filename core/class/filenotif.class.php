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

    public function checkfile() {
      $folder = $this->getConfiguration('foldertocheck');

      if ($folder == "") {
        log::add('filenotif', 'info', 'Configuration du répertoire à surveiller manquante');
        return false;
      }

      $oldMD5 = $this->getConfiguration('FolderMD5');
      $ext = $this->getConfiguration('extensiontocheck');
      $subdir = $this->getConfiguration('checksubdir');

      if ($subdir == 1) {
        foreach (glob($folder . "/*", GLOB_ONLYDIR) as $newdirfound)
          {
              $lstfolder[] = $newdirfound;
          }
      } else {
        $lstfolder[] = $folder;
      }

      log::add('filenotif', 'debug', 'Nombre de répertoire à lire : '.count($lstfolder));

      $listedfiles = array();
      foreach ($lstfolder as $dir) {

        if (substr($dir, -1) != "/") {
          $dir .= "/";
        }

        if ($ext == '*' OR $ext == NULL ) {
          log::add('filenotif', 'debug', 'Lecture de : '.$dir. " En mode *");
          $newfilesfound = glob($dir.'*');
          $listedfiles = array_merge($listedfiles, $newfilesfound);
        } else {
          log::add('filenotif', 'debug', 'Lecture de : '.$dir. " En mode BRACE :".$ext);
          $newfilesfound = glob($dir."*.{".$ext."}", GLOB_BRACE);
          $listedfiles = array_merge($listedfiles, $newfilesfound);
        }
      }

      $newMD5 = md5(print_r($listedfiles, true));
      $newCount = count($listedfiles);
      $this->checkAndUpdateCmd('files_quantity', $newCount);

      log::add('filenotif', 'debug', 'Glob retourne : '.print_r($listedfiles, true));
      log::add('filenotif', 'debug', 'Nombres de fichiers : '.$newCount);
      $this->setConfiguration('FolderMD5',$newMD5);
      $this->save();

      log::add('filenotif', 'debug', 'MD5(new)= '.$newMD5);
      log::add('filenotif', 'debug', 'MD5(old)= '.$oldMD5);
      if ($oldMD5 != $newMD5) {
        /*Example array.
        $array = array('Ireland', 'England', 'Wales', 'Northern Ireland', 'Scotland');

        //Serialize the array.
        $serialized = serialize($array);

        //Save the serialized array to a text file.
        file_put_contents('serialized.txt', $serialized);

        //Retrieve the serialized string.
        $fileContents = file_get_contents('serialized.txt');

        //Unserialize the string back into an array.
        $arrayUnserialized = unserialize($fileContents);

        //End result.
        var_dump($arrayUnserialized);*/
        $file = dirname(__FILE__).'/files.dat';
        log::add('filenotif', 'debug', 'Chemin de la sauvegarde : '.$file);

          // Save Folder/Files structures to file
          try {


              if (file_put_contents($file, serialize($listedfiles))) {
                log::add('filenotif', 'debug', 'Ecriture du fichier OK '.$file);
              } else {
                log::add('filenotif', 'debug', 'Ecriture du fichier NOK '.$file);
              }
              chmod($file, 0770);
          } catch (Exception $e) {
              log::add('filenotif', 'debug', 'Erreur : '.$e->getMessage());
          }

          log::add('filenotif', 'debug', '=> Changement détecté !');
          $oldCount = $this->getConfiguration('FilesCount', 0);
          $deltaCount = $newCount - $oldCount;
          $this->setConfiguration('FilesCount', $newCount);
          $this->save();
          $this->checkAndUpdateCmd('info_filecount', $deltaCount);
          log::add('filenotif', 'debug', 'Comptage | Delta='.$deltaCount.' OLD='.$oldCount." NEW=".$newCount);
          $notifmode = $this->getConfiguration('notifmode');
          if ($notifmode == "newfile" && $deltaCount > 0) {
            // New file ADDED
            filenotif::raisenotif(10);
          } elseif ($notifmode == "delfile" && $deltaCount < 0) {
            // File Deleted
            filenotif::raisenotif(10);
          } elseif ($notifmode == "bothfile" && $deltaCount != 0) {
            // BOTH DEL&NEW
            filenotif::raisenotif(10);
          } else {
            log::add('filenotif', 'debug', 'Pas de notification');
          }
      } else {
        log::add('filenotif', 'debug', '=> RAS');
      }
    }

    public function raisenotif($duration) {
      log::add('filenotif', 'debug', '> Notification envoyé');
      $this->checkAndUpdateCmd('flag_newfile', 1);
      sleep($duration);
      $this->checkAndUpdateCmd('flag_newfile', 0);
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
    						$filenotif->checkfile();
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
      $filenotifCmd->setName(__('Nombre de fichier ajouter ou supprimer', __FILE__));
      $filenotifCmd->setEqLogic_id($this->id);
      $filenotifCmd->setType('info');
      $filenotifCmd->setSubType('string');
      $filenotifCmd->setIsHistorized(0);
      $filenotifCmd->setLogicalId('info_filecount');
      $filenotifCmd->setOrder(3);
      $filenotifCmd->save();

      /*$filenotifCmd = new filenotifCmd();
      $filenotifCmd->setName(__('Noms des fichiers', __FILE__));
      $filenotifCmd->setEqLogic_id($this->id);
      $filenotifCmd->setType('info');
      $filenotifCmd->setSubType('string');
      $filenotifCmd->setIsHistorized(0);
      $filenotifCmd->setLogicalId('files_listing');
      $filenotifCmd->setOrder(4);
      $filenotifCmd->save();*/

      $filenotifCmd = new filenotifCmd();
      $filenotifCmd->setName(__('Quantités de fichier', __FILE__));
      $filenotifCmd->setEqLogic_id($this->id);
      $filenotifCmd->setType('info');
      $filenotifCmd->setSubType('numeric');
      $filenotifCmd->setIsHistorized(0);
      $filenotifCmd->setLogicalId('files_quantity');
      $filenotifCmd->setOrder(5);
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
       $eqLogic->checkfile($result);
     }

    /*     * **********************Getteur Setteur*************************** */
}
