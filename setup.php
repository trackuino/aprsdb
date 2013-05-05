<?php
$vars = array(
  'home'  => '/home/aprs',
  'user' => 'aprs',
  'db' => 'aprs',
  'callsign' => 'XXXXXX',
);

$descriptions = array(
  'home' => 'Home directory',
  'user' => 'MySQL user name',
  'db' => 'MySQL database name',
  'callsign' => 'Your callsign',
);


foreach ($vars as $k => $v) {
  if (isset($_REQUEST[$k])) $vars[$k] = $_REQUEST[$k];
}

$t = time();
$d = 86400;
$p1n = date('\pYmd', $t + 1 * $d);
$p2n = date('\pYmd', $t + 2 * $d);
$p1t = date('Y-m-d 00:00:00', $t + 1 * $d);
$p2t = date('Y-m-d 00:00:00', $t + 2 * $d);
$estart = date('Y-m-d 03:00:00', $t);
?>
<html>
<head>
  <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
  <title>Apr2db setup</title>
  <style type="text/css">
    pre {color:blue; margin-left:4em; padding: 0.5em; margin-right: 20%; border-style:dashed; border-width:1px; border-color: Navy;}
  </style>
</head>
<body>
  <h1>Aprs2db setup</h1>
  <p>
    Some values can be customized for your specific setup. Change those
    values in the form below and click "Reload" to refresh the instructions.
  </p>
     
  <form method="get" action="">
    <table>
<?php foreach ($vars as $k => $v) { ?>
    <tr>
      <td><label for="<?=$k?>"><?=$descriptions[$k]?></label></td>
      <td><input type="text" name="<?=$k?>" value="<?=$v?>"></td>
    </tr>
<?php } ?>
    </table>
    <input type="submit" name="submit" value="Reload instructions">
  </form>
  <h2>Step 1: database setup</h2>
  <p>
    First off, enable partitioning in MySQL by copying mysql/aprs2db.cnf over to /etc/mysql/conf.d/:
  </p>
  <pre>
cp mysql/aprs2db.conf /etc/mysql/conf.d
restart mysql
  </pre>
  <p>
    Paste the following SQL script to a text file (eg. aprs2db.sql). For efficient rotation
    of old data, two partitions are created automatically for today and tomorrow's data 
    and an event handles daily rotation
    from there on. The script is dependent on today's date, so run it right after a refresh
    of this page.
  </p>
  <pre>
CREATE DATABASE `<?=$vars['db']?>` DEFAULT CHARACTER SET utf8;

GRANT ALL PRIVILEGES ON `<?=$vars['db']?>`.* TO `<?=$vars['user']?>`@localhost;

USE `<?=$vars['db']?>`;

DROP TABLE IF EXISTS `packets`;
CREATE TABLE `packets` (
        `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `callsign` varchar(10) DEFAULT NULL,
        `data` varchar(255) DEFAULT NULL,
        INDEX idx_ts (ts),
  INDEX idx_callsign (callsign)
) 
ENGINE=InnoDB
DEFAULT CHARSET=utf8 
PARTITION BY RANGE ( UNIX_TIMESTAMP(ts) ) (
  PARTITION <?= $p1n ?> VALUES LESS THAN ( UNIX_TIMESTAMP('<?= $p1t ?>') ),
  PARTITION <?= $p2n ?> VALUES LESS THAN ( UNIX_TIMESTAMP('<?= $p2t ?>') )
);

DROP EVENT IF EXISTS Aprs_Partitions;

DELIMITER |
CREATE EVENT Aprs_Partitions
ON SCHEDULE EVERY 1 DAY STARTS '<?=$estart?>'
DO
BEGIN
  -- ignore exceptions
  DECLARE d DATE;
  DECLARE ut_new VARCHAR(20);
  DECLARE pn_new VARCHAR(10);
  DECLARE pn_old VARCHAR(10);
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  SELECT CURDATE() INTO d;
  SELECT DATE_FORMAT(DATE_ADD(d, INTERVAL 2 DAY), '%Y-%m-%d 00:00:00') INTO ut_new;
  SELECT DATE_FORMAT(DATE_ADD(d, INTERVAL 2 DAY), 'p%Y%m%d') INTO pn_new;
  SELECT DATE_FORMAT(DATE_SUB(d, INTERVAL 3 DAY), 'p%Y%m%d') INTO pn_old;

  SET @q = CONCAT('ALTER TABLE packets ADD PARTITION (PARTITION ',
    pn_new,
    ' VALUES LESS THAN (UNIX_TIMESTAMP(\'',
    ut_new,
    '\')))');
  PREPARE q FROM @q;
  EXECUTE q;
  DEALLOCATE PREPARE q;

  SET @q = CONCAT('ALTER TABLE packets DROP PARTITION ', pn_old);
  PREPARE q FROM @q;
  EXECUTE q;
  DEALLOCATE PREPARE q;
END |
DELIMITER ;
  </pre>
  <p>
    Run it:
  </p>
  <pre>
mysql -uroot -p[your mysql root password] &lt;aprs2db.sql
  </pre>
  <h2>Step 2: daemon setup</h2>
  <p>
    As root, create /etc/default/aprs2db with the following contents:
  </p>
  <pre>
cat &gt;/etd/default/aprs2db
  </pre>
  <p>Then paste (end with CTRL+D):</p>
  <pre>
# Where aprs2db.pl lives
APRS_HOME=<?=$vars['home']?>


# aprs2db.pl options (see aprs2db.pl --help)
OPTIONS="-c <?=$vars['callsign']?>"
  </pre>
  <p>
    Create user 'aprs'. On a root shell::
  </p>
  <pre>
useradd -s /bin/bash -m -U aprs
  </pre>
  <p>
    Copy over the aprs2db daemon to its home:
  </p>
  <pre>
cp daemon/aprs2db.pl <?=$vars['home']?>
chmod +x <?=$vars['home']?>/aprs2db.pl
  </pre>
  <p>
    Copy over the upstart script to /etc/init:
  <p>
  <pre>
cp upstart/aprs2db.conf /etc/init
  </pre>
  <p>  
    Install the basic gcc+make tools as root:
  </p>
  <pre>
apt-get install build-essential  
  </pre>
  <p>
    Then login as the aprs user and
    install Ham::APRS Cpan modules. These will be installed as local modules in ~/perl5
    and the appropriate env vars will be added to your .bashrc
  </p>
  <pre>
su - aprs
wget -O- http://cpanmin.us | perl - -l ~/perl5 App::cpanminus local::lib
eval `perl -I ~/perl5/lib/perl5 -Mlocal::lib`
echo 'eval `perl -I ~/perl5/lib/perl5 -Mlocal::lib`' &gt;&gt; ~/.bashrc
cpanm Ham::APRS::IS
cpanm Ham::APRS::FAP
  </pre>
  <p>
    You can launch the aprs2db daemon now. Exit the 'su' shell to become root again, and do:
  </p>
  <pre>
exit
start aprs2db
  </pre>
  <h2>Step 3: web app setup</h2>
  <p>Install apache + PHP. You should have done this already if you are seeing this page:</p>
<pre>
apt-get install apache2 php5 libapache2-mod-php5
</pre>
  <p>Create a web directory in the aprs2db home and copy the the web app over:</p>
  <pre>
mkdir <?=$vars['home']?>/web
cp web/index.php <?=$vars['home']?>/web
  </pre>
  <p>Create the config file:</p>
  <pre>
cat &gt;<?=$vars['home']?>/web/config.php
  </pre>
  <p>Then paste:</p>
  <pre>
&lt;?php
define('SERVER', '127.0.0.1');
define('USER',   '<?=$vars['user']  ?>');
define('PASS',   '');
define('DB',     '<?=$vars['db']   ?>');
?&gt;
  </pre>
  <p>
    Create an apache2 site. Customize port, ServerAdmin, ServerName and log paths as you wish. 
    The logrotate daemon takes care of log rotation if we log to the default /var/log/apache2 
    path.
  </p>
  <pre>
cat &gt;/etc/apache2/sites-available/aprs
  </pre>
  <p>Then paste:</p>
  <pre>
Listen 8000
&lt;VirtualHost *:8000&gt;
  ServerAdmin webmaster@localhost

  DocumentRoot <?=$vars['home']/web?>
  ServerName aprs.trackuino.org

  ErrorLog  /var/log/apache2/aprs-error.log
  CustomLog /var/log/apache2/aprs-access.log combined
  
  php_flag log_errors on
  php_value error_log /var/log/apache2/aprs-php.log
&lt;/VirtualHost&gt;
  </pre>
  <p>Enable the site and restart apache:</p>
  <pre>
a2ensite aprs
/etc/init.d/apache2 restart
  </pre>
  <h2>Test</h2>
  <p>Browse http://yourserver?c=CALLSIGN where yourserver is your virtual host's ServerName and 
  CALLSIGN is a known beaconing CALLSIGN. You should see one or more lines of APRS packet 
  data.</p>
  <h2>If something goes wrong</h2>
  <p>Aprs2db logs are in /var/log/upstart/aprs2db.log, check there for errors.</p>
  <p>Apache logs are in /var/log/apache2/aprs-*.log unless you changed the default log 
  paths.</p>
</body>
</html>
