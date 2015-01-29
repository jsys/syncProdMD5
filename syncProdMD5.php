<?php
/*
  syncProdMD5.php 
  Prg par Jérôme Saynes le 29/01/2015
  Licence GPL v3

  1. Modifier la CONFIG
  2. uploader ce fichier en prod à l'url indiqué dans la config
  3. Appelez ce fichier en local pour afficher la différence entre le local et la prod
  4. Cliquez sur les liens pour uploader ou downloader les différences 

 */
 
// *********************** CONFIG *********************************
 
// Remplacer par une chaine unique pour sécuriser l'accès
$CONFIG['token']='ABCDEFGHIJKLMNOPQRSTUVWXYZ'; 

// Serveur ou se trouve ce fichier en ligne
$CONFIG['SERVER_LIGNE']='www.exemple.com'; 

// Url du fichier en ligne
$CONFIG['URL_FICHIER_SERVEUR']='http://www.exemple.com/syncProdMD5.php'; 

// Liste des dossiers à synchroniser
$CONFIG['dossiers']=array(
    '.',
    'css',
    'fonts',
    'js',
    'php'
);

// *********************** CONFIG FACULTATIVE *********************************

// Facultatif : Si besoin d'un cache
$CONFIG['CACHE_TIME']=0; // 0 = pas de cache = config par défaut. Durée en seconde
$CONFIG['CACHE_FILE']='syncProdMD5.cache.php'; // Nom du fichier de cache 

// Facultatif : Synchro de la structure de la base de donnée
// Accès à la BDD locale
$CONFIG['LOCAL_DB_SERVER']='127.0.0.1';
$CONFIG['LOCAL_DB_USERNAME']='user';
$CONFIG['LOCAL_DB_PASSWORD']='pass';
$CONFIG['LOCAL_DB_NAME']='mabase';

// Accès à la BDD en ligne
$CONFIG['LIGNE_DB_SERVER']='127.0.0.1';
$CONFIG['LIGNE_DB_USERNAME']='userenligne';
$CONFIG['LIGNE_DB_PASSWORD']='passenligne';
$CONFIG['LIGNE_DB_NAME']='mabaseprod';

// Facultatif : Utilisez FineDiff pour visualiser le diff entre local et prod
$CONFIG['FILE_FINEDIFF']=false // 'chemin/FineDiff.php';


// ********************** FIN DE LA CONFIG ************************************
$CONFIG['version']='2015-01-27'; // Version de ce fichier (ne pas toucher)

function md5dossier($dir) {
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (!is_dir($file)&&$file!=='.gitignore') {
                    $md5=md5_file("$dir/$file");
                    ($dir=='.') ? $key=$file : $key="$dir/$file";
                    $res[$key]=$md5.'-'.filemtime("$dir/$file");
                }
            }
            closedir($dh);
        }
    }
    ksort($res);
    return $res;
}


function nettoie($s) {
    $s=trim(str_replace("\r","",$s));
    $s=trim(str_replace("\n","",$s));
    return $s;
}


function mysql2json($server, $username, $password, $basename) {
    @mysql_connect($server, $username, $password) or die("Erreur connexion : ".mysql_error());
	@mysql_select_db($basename) or die("Erreur selection de la base ".mysql_error());
	$rq=mysql_query('SHOW TABLES');
	while($c=mysql_fetch_row($rq)) {
        $rq1=mysql_query('show create table '.$c[0]);
        if ($rq1)
        while($c1=mysql_fetch_array($rq1, MYSQL_ASSOC)) {
            $res[]=$c1['Create Table'];
            $res[]='';
        }
    }
    return implode("\n",$res);
}

// Je suis en ligne
if ($_SERVER['SERVER_NAME']==$CONFIG['SERVER_LIGNE']) {
    if (!isset($_GET['token']) or $_GET['token']!=$CONFIG['token']) die(json_encode(array('message'=>"token invalide")));
    if (!isset($_GET['version']) or $_GET['version']!=$CONFIG['version']) die(json_encode(array('message'=>"Version differente entre le client et le serveur")));

    if (isset($_POST['name']) && isset($_POST['content'])) {
        file_put_contents($_POST['name'], $_POST['content']);
        die('OK');
    }

    if ($_GET['file']<>'') {
        if ($_GET['file']=='mysql') {
            echo mysql2json($CONFIG['LIGNE_DB_SERVER'],$CONFIG['LIGNE_DB_USERNAME'],$CONFIG['LIGNE_DB_PASSWORD'],$CONFIG['LIGNE_DB_NAME']);
        } else {
            echo file_get_contents($_GET['file']);
        }
    } else {
        // Je revois tout les fichiers
        $local=array();
        foreach($CONFIG['dossiers'] as $dossier) $local=array_merge($local,md5dossier($dossier));
        echo json_encode($local);
    }

}
elseif (isset($_GET['diff'])&& $CONFIG['FILE_FINEDIFF']) {
    include $CONFIG['FILE_FINEDIFF'];
    if ($_GET['diff']=='mysql') {
        $local=mysql2json($CONFIG['LOCAL_DB_SERVER'],$CONFIG['LOCAL_DB_USERNAME'],$CONFIG['LOCAL_DB_PASSWORD'],$CONFIG['LOCAL_DB_NAME']);
    } else {
        $local=file_get_contents($_GET['diff']);
    }    
    $ligne=file_get_contents($CONFIG['URL_FICHIER_SERVEUR'].'?token='.$CONFIG['token'].'&version='.$CONFIG['version'].'&file='.$_GET['diff']);
    $opcodes = FineDiff::getDiffOpcodes($ligne,$local);
    $render = FineDiff::renderDiffToHTMLFromOpcodes($ligne, $opcodes);

    echo '<html><head>
    <style type="text/css">
    body {margin:0;border:0;padding:0;font:11pt sans-serif}
    body > h1 {margin:0 0 0.5em 0;font:2em sans-serif;background-color:#def}
    body > div {padding:2px}
    p {margin-top:0}
    ins {color:green;background:#dfd;text-decoration:none}
    del {color:red;background:#fdd;text-decoration:none}
    .pane {margin:0;padding:0;border:solid 1px;width:100%;min-height:20em;overflow:auto;font:12px monospace;white-space:pre-wrap}
    #htmldiff {color:gray}
    #htmldiff.onlyDeletions ins {display:none}
    #htmldiff.onlyInsertions del {display:none}
    </style>
    </head><body>
    <div>'.$opcodes.'</div><hr>
    <div><ins>Present en local mais pas en ligne</ins> <del>Present en ligne mais pas en local</del></div>
    <div id="htmldiff" class="pane" style="">'.$render.'</div>
    <div class="pane" style="width:40%;float:left">LOCAL
    '.htmlentities($local).'</div>
    <div class="pane" style="width:40%">LIGNE
    '.htmlentities($ligne).'</div>
    ';
}
else {

    if (isset($_GET['syncdl'])) {
        $ligne=file_get_contents($CONFIG['URL_FICHIER_SERVEUR'].'?token='.$CONFIG['token'].'&version='.$CONFIG['version'].'&file='.$_GET['syncdl']);
        file_put_contents($_GET['syncdl'], $ligne);
        header('Location: ?');
        die();
    } 
    
    if (isset($_GET['syncup'])) {
        $url=$CONFIG['URL_FICHIER_SERVEUR'].'?token='.$CONFIG['token'].'&version='.$CONFIG['version'];
        $local=file_get_contents($_GET['syncup']);
        $data = array('name' => $_GET['syncup'], 'content' => $local);
        $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ));
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result=='OK') {
            header('Location: ?');
            die();
        } else die('PB upload');    
    } 

    if ($CONFIG['CACHE_TIME']>0 and file_exists($CONFIG['CACHE_FILE']) and time()-filemtime($CONFIG['CACHE_FILE'])<$CONFIG['CACHE_TIME']) {
        $ligne=(array)json_decode(file_get_contents($CONFIG['CACHE_FILE']));
    } else {
        $json=file_get_contents($CONFIG['URL_FICHIER_SERVEUR'].'?token='.$CONFIG['token'].'&version='.$CONFIG['version']);

        if ($CONFIG['CACHE_TIME']>0) {
            file_put_contents($fic, $json);
        }
        $ligne=(array)json_decode($json);
    }

    if (isset($ligne['message'])) die("Reponse du serveur : ".$ligne['message']);

    $local=array();
    foreach($CONFIG['dossiers'] as $dossier) $local=array_merge($local,md5dossier($dossier));

    $res=array();
    foreach($local as $klo=>$vlo) {
        $s=false;
        if (isset($ligne[$klo])) {
            list($md5lo,$timelo)=explode('-',$vlo);
            if (isset($ligne[$klo])) list($md5li,$timeli)=explode('-',$ligne[$klo]);
            if ($md5lo<>$md5li) {
                if ($timelo>$timeli) {
                    $s="local => ligne"; 
                } else {
                    $s="local <= ligne";
                }    
            } 
        } else $s='Absent en ligne';

        if ($s or isset($_GET['tous'])) {
            $res[$klo]=$s;
        }
    }
    
    foreach($ligne as $kli=>$vli) {
        $s=false;
        if (isset($local[$kli])) list($md5lo,$timelo)=explode('-',$local[$kli]);
        list($md5li,$timeli)=explode('-',$vli);
            
        if (isset($local[$kli])) {
            if ($md5lo<>$md5li) {
                if ($md5lo<>$md5li) {
                    if ($timelo>$timeli) {
                        $s='<a href="?syncup='.$kli.'">local => ligne</a>'; 
                    } else {
                        $s='<a href="?syncdl='.$kli.'">local <= ligne</a>';
                    }    
                } 
            }
        } else $s='<a href="?syncdl='.$kli.'">Absent local <= ligne</a>';

        if ($s or isset($_GET['tous'])) {
            $res[$kli]=$s;
        }
    }
      
    ksort($res);
    echo '<div>'.count($res).' fichiers differents | <a href="?tous=1">Tout afficher</a></div>';
    if (count($res)>0) {
        echo '<table border=1 cellspacing=0>';
        foreach($res as $k=>$v) {
            echo "<tr>
            <td>$k</td>
            <td>$v</td>
            <td><a href=\"?diff=$k\">Diff</a></td>
            </tr>
            \n";
        }
        echo "</table>";
    }
}
?>