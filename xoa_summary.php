<?php

require_once 'Console/Table.php';
require_once 'Console/CommandLine.php';

define('DEFAULT_JSON_PATH', '/tmp/');
define('DEFAULT_XO_CLI_PATH', '/usr/local/bin/xo-cli');

$parser = new Console_CommandLine();
$parser->description = 'Get a XenServer tree view of pool/host/vm/sr.'."\n";
$parser->description .= "\n";
$parser->description .= "It uses the Xen-Orchestra xo-cli (https://xen-orchestra.com/docs/xo-cli.html) command to get information from XenServer pools. You have to run once xo-cli --register to register the client to your xo-server.\n";
$parser->description .= "This script reads data from files: pool.json, host.json, SR.json, VM.json, VBD.json, VDI.json. ";
$parser->description .= "To have them up to date, you should add a cron like:\n";
$parser->description .= '* * * * * php xoa_summary.php --cron';

$parser->version = '1.0.0';
$parser->addOption('showvms', array(
                       'short_name'  => '-v',
                       'long_name'   => '--show-vms',
                       'description' => 'show VMs.',
                       'action'      => 'StoreTrue',
                       'default'     => FALSE,
                       ));
$parser->addOption('inverse', array(
                       'short_name'  => '-i',
                       'description' => "Instead displays used values, display available values.",
                       'action'      => 'StoreTrue',
                       'default'     => FALSE,
                       ));
$parser->addOption('json_path', array(
                       'short_name'  => '-p',
                       'long_name'   => '--json-path',
                       'description' => "Path to folder containing pool.json, host.json, SR.json, VM.json, VBD.json, VDI.json files.\nDefault is current directory.\nExemple to create files : \n".'for i in VDI VM SR host pool VBD; do '."\n".'   xo-cli --list-objects type=$i >'."\n".'    /tmp/$i.tmp;'."\n".'done;'."\n".'for i in VDI VM SR host pool VBD; do '."\n".'   mv /tmp/$i.tmp /tmp/$i.json;'."\n".'done'."\n",
                       'help_name'   => 'PATH',
                       'action'=>'StoreString',
                       'default'     => DEFAULT_JSON_PATH,
                       ));
$parser->addArgument('search', array(
                         'description' => 'Limit display to pool where search is found in "pool name", or "vm name" or "sr name" or "host name". Search is done case-insensitive.',
                         'optional' => TRUE,
                         'multiple' => FALSE,
                         ));

$parser->addOption('cron', array(
                       'description' => 'Usefull when run by cron to create data files.',
                       'long_name'   => '--cron',
                       'action'      => 'StoreTrue',
                       'default'     => FALSE,
                       ));
$parser->addOption('xo_cli_path', array(
                       'description' => 'xo-cli path.',
                       'long_name'   => '--xo-cli',
                       'action'      => 'StoreString',
                       'default'     => DEFAULT_XO_CLI_PATH,
                       'help_name'   => 'PATH',
                       ));

$parser->addOption('hook', array(
                       'description' => 'hook run after files update.',
                       'long_name'   => '--hook',
                       'action'      => 'StoreString',
                       'default'     => NULL,
                       ));

$parser->addOption('debug', array(
                       'description' => 'Set debug mode (only usefull with --cron).',
                       'short_name'  => '-d',
                       'long_name'   => '--debug',
                       'action'      => 'StoreTrue',
                       'default'     => FALSE,
                       ));

try
{
    $result = $parser->parse();
}
catch (Exception $exc)
{
    $parser->displayError($exc->getMessage());
}

$show_vms = $result->options['showvms'];
$search = $result->args['search'];
$show_used_values = $result->options['inverse'];
$json_file_path = $result->options['json_path'];
$debug = $result->options['debug'];
$cron = $result->options['cron'];
$hook = $result->options['hook'];

if (!file_exists($json_file_path) || !is_dir($json_file_path))
{
    file_put_contents('php://stderr', "Error: '$json_file_path'".' path does not exists or is not a directory.'."\n");
    exit(1);
}

if ($cron === TRUE)
{
    $xo_cli_path = $result->options['xo_cli_path'];
    if (!file_exists($xo_cli_path) || !is_executable($xo_cli_path))
    {
        file_put_contents('php://stderr', "Error: '$xo_cli_path'".' does not exists or is not executable'." Use --xo-cli to the a correct path.\n");
		exit(1);
    }
    if (!file_exists($xo_cli_path) || !is_executable($xo_cli_path))
    {
        file_put_contents('php://stderr', "Error: '$xo_cli_path'".' does not exists or is not executable'." Use --xo-cli to the a correct path.\n");
		exit(1);
    }
    if (!is_writable($json_file_path))
    {
        file_put_contents('php://stderr', "Error: '$json_file_path' permission denied.\n");
		exit(1);
    }
    foreach ([ 'VDI', 'VM', 'SR', 'host', 'pool', 'VBD' ] as $obj)
    {
        if ($debug)
        {
            file_put_contents('php://stderr', $xo_cli_path." --list-objects type=$obj > $json_file_path/$obj.json-tmp\n");
        }
        exec($xo_cli_path." --list-objects type=$obj > $json_file_path/$obj.json-tmp", $output, $return_var);
        if ($return_var != 0)
        {
            file_put_contents('php://stderr', implode("\n", $output));
            exit ($return_var);
        }
        if ($debug)
        {
            file_put_contents('php://stderr', "rename $json_file_path/$obj.json-tmp to $json_file_path/$obj.json\n");
        }
        rename("$json_file_path/$obj.json-tmp", "$json_file_path/$obj.json");
    }

    $return_var = 0;
    if (!$ret && $hook)
    {
        echo system($hook, $return_var);
    }
    exit($return_var);
}

if ($show_used_values)
{
    $header_used_or_available_txt = 'used';
}
else
{
    $header_used_or_available_txt = 'available';
}


function size_bar($used, $total)
{
    $txt = '[';
    if ($total < 1)
    {
        $percent = 100;
    }
    else
    {
        $percent = $used * 100 / $total;
    }
    $i = 0;
    while ($i < 10)
    {
        if ($percent / 10 > $i)
            $txt .= '*';
        else
            $txt .= ' ';
        $i++;
    }
    $txt .= ']';
    return $txt;
}

function pretty_print_2val($n1, $n2, $dec = 0, $with_unit = TRUE, $nb_char = 7)
{
    $size = array('B', 'kiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB');
    $loc_bytes = $n1;
    $factor1 = 0;
    while ($loc_bytes >= 1024)
    {
        $loc_bytes /= 1024;
        $factor1++;
    }
    $loc_bytes = $n2;
    $factor2 = 0;
    while ($loc_bytes >= 1024)
    {
        $loc_bytes /= 1024;
        $factor2++;
    }

    $factor = ($factor2 > $factor1) ? $factor2 : $factor1;

    $unit = ($with_unit ? ' ' . @$size[$factor] : '');
    return sprintf("%$nb_char.{$dec}f / %$nb_char.{$dec}f%s", $n1 / pow(1024, $factor), $n2 / pow(1024, $factor), $unit);
}

function pretty_print($bytes, $dec = 0, $with_unit = TRUE, $nb_char = 7)
{
    $size = array('B', 'kiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB');
    $loc_bytes = $bytes;
    $factor = 0;
    while ($loc_bytes >= 1024)
    {
        $loc_bytes /= 1024;
        $factor++;
    }

    $unit = ($with_unit ? ' ' . @$size[$factor] : '');
    return sprintf("%$nb_char.{$dec}f", $bytes / pow(1024, $factor)) . $unit;
}

function get_halted_vms($pool)
{
    $halted_vms = [];
    if (isset($pool['vms']))
    {
        foreach ($pool['vms'] as $vm)
        {
            $vm_disk_used = 0;
            if (isset($vm['VDIs']))
            {
                foreach ($vm['VDIs'] as $vdi)
                {
                    $vm_disk_used += $vdi['size'];
                }
            }
            $halted_vms[] = [
                $vm['name_label'],
                $vm['CPUs']['max'],
                pretty_print($vm['memory']['size']),
                pretty_print($vm_disk_used)
                ];
        }
    }

    return $halted_vms;
}

function get_sr_infos($sr, $show_used_values)
{
    $nb_pbd = count($sr['$PBDs']);
    if ($nb_pbd > 1)
        $pbd_txt = '+ ';
    elseif ($nb_pbd == 1)
        $pbd_txt= '  ';
    else
        $pbd_txt= '? ';

    $tmp_sr_space = $sr['physical_usage'];
    if ($show_used_values === FALSE)
    {
        $tmp_sr_space = $sr['size'] - $sr['physical_usage'];
    }
    return [
        ($pbd_txt).$sr['name_label'],
        size_bar($tmp_sr_space, $sr['size'], TRUE, 1).' '.pretty_print_2val($tmp_sr_space, $sr['size'], 2, TRUE, 7)
        ];
}

function get_host_info($host, $show_vms, $pool, $show_used_values)
{
    $cpu_used = 0;
    $disk_used = 0;
    $ram_used = 0;
    $vms_list = [];
    $vms_total = [];
    $nb_running_vms = isset($host['vms']) ? count($host['vms']) : 0;
    if (isset($host['vms']))
    {
        $locvms = [];
        if (count($host['vms']) > 0)
        {
            foreach ($host['vms'] as $vm)
            {
                $vm_disk_used = 0;
                if (isset($vm['VDIs']))
                {
                    foreach ($vm['VDIs'] as $vdi)
                    {
                        $vm_disk_used += $vdi['size'];
                        $disk_used += $vdi['size'];
                    }
                }
                $vms_list[] = [
                    $vm['name_label'],
                    $vm['CPUs']['number'],
                    pretty_print($vm['memory']['size']),
                    pretty_print($vm_disk_used)
                    ];
                $cpu_used += $vm['CPUs']['number'];
                $ram_used += $vm['memory']['size'];


            }
            $vms_total = [
                'Total: '.$nb_running_vms.' runnings vms',
                $cpu_used,
                pretty_print($ram_used),
                pretty_print($disk_used)
                ];
        }
    }

    $host_info_name = $host['name_label'].' ('.$host['version'].')';
    if ($pool['master'] == $host['uuid'])
    {
        $host_info_name = '+ '.$host_info_name;
    }
    else
    {
        $host_info_name = '  '.$host_info_name;
    }

    $tmp_cpus = $cpu_used;
    $tmp_mem = $host['memory']['usage'];
    if ($show_used_values === FALSE)
    {
        $tmp_cpus = $host['CPUs']['cpu_count'] - $cpu_used;
        $tmp_mem = $host['memory']['size']-$host['memory']['usage'];
    }
    $host_info_cpu = size_bar($tmp_cpus, $host['CPUs']['cpu_count']).' '.pretty_print_2val($tmp_cpus, $host['CPUs']['cpu_count'], 0, FALSE, 2);
    $host_info_mem = size_bar($tmp_mem,  $host['memory']['size']).' '.   pretty_print_2val($tmp_mem,  $host['memory']['size'],    2, TRUE, 6);

    $host_infos = [
        'name' => $host_info_name,
        'cpu' => $host_info_cpu,
        'mem' => $host_info_mem
        ];

    if ($show_vms)
    {
        $host_infos['vms'] = $vms_list;
        $host_infos['vm_total'] = $vms_total;
    }

    return [
        'infos' => $host_infos,
        'cpu_used' => $cpu_used,
        'ram_used' => $ram_used,
        'disk_used' => $disk_used,
        ];
}


$json_base_files_name = [ 'pool', 'host', 'SR', 'VM', 'VBD', 'VDI' ];
$json = [];
foreach ($json_base_files_name as $json_file_name)
{
    $json[$json_file_name] = @json_decode(file_get_contents($json_file_path.'/'.$json_file_name.'.json'), true);
    if (!is_array($json[$json_file_name]))
    {
        file_put_contents('php://stderr', 'Error while decoding file "'.$json_file_path.'/'.$json_file_name.'.json"'."\n");
        exit(1);
    }
}

$pools = [];
$pools_to_display = [];
foreach ($json['host'] as $host)
{
    $pool_uuid = $host['$pool'];
    $pools[$pool_uuid]['hosts'][] = $host;

    if ($search !== NULL && stristr($host['name_label'], $search) !== FALSE)
    {
        $pools_to_display[] = $pool_uuid;
    }
}
foreach ($json['VM'] as $VM)
{
    foreach ($VM['$VBDs'] as $vbd)
    {
        foreach ($json['VDI'] as $vdi)
        {
            foreach ($vdi['$VBDs'] as $vdi_vbd)
            {
                if ($vdi_vbd == $vbd)
                {
                    $VM['VDIs'][] = $vdi;
                }
            }
        }
    }

    $pool_uuid = $VM['$pool'];
    $itx = 0;
    $found = false;
    for ($itx = 0; $itx < count($pools[$pool_uuid]['hosts']); $itx++)
    {
        $host = $pools[$pool_uuid]['hosts'][$itx];
        if ($host['uuid'] == $VM['$container'])
        {
            $pools[$pool_uuid]['hosts'][$itx]['vms'][] = $VM;
            $found = true;
        }
    }

    if (!$found)
    {
        $pools[$pool_uuid]['vms'][] = $VM;
    }

    if ($search !== NULL && stristr($VM['name_label'], $search) !== FALSE)
    {
        $pools_to_display[] = $pool_uuid;
    }
}

foreach ($json['SR'] as $sr)
{
    $pool_uuid = $sr['$pool'];
    $pools[$pool_uuid]['srs'][] = $sr;
    if ($search !== NULL && stristr($sr['name_label'], $search) !== FALSE)
    {
        $pools_to_display[] = $pool_uuid;
    }
}

foreach ($json['pool'] as $pool)
{
    $pool_uuid = $pool['uuid'];
    $pools[$pool_uuid]['uuid'] = $pool['uuid'];
    $pools[$pool_uuid]['master'] = $pool['master'];
    $pools[$pool_uuid]['name_description'] = $pool['name_description'];
    $pools[$pool_uuid]['name_label'] = $pool['name_label'];
    if ($search !== NULL && stristr($pool['name_label'], $search) !== FALSE)
    {
        $pools_to_display[] = $pool_uuid;
    }
}


$tmp_pools = [];
foreach ($pools as $pool)
{
    if ($search !== NULL && !in_array($pool['uuid'], $pools_to_display))
        continue;
    $tmp_pools[] = $pool;
}
$pools = $tmp_pools;

$pool_rows = [];
$itx_pool = 0;
foreach ($pools as $pool)
{
    if ($search !== NULL && !in_array($pool['uuid'], $pools_to_display))
        continue;

    $master_host = NULL;
    $pool_cpu_used = 0;
    $pool_disk_used = 0;
    $pool_ram_used = 0;

    /**************************\
     *       Hosts & VMs        *
     \**************************/
    $pool_hosts = [];
    $pool_halted_vms = [];
    foreach ($pool['hosts'] as $host)
    {
        if ($pool['master'] == $host['uuid'])
        {
            $master_host = $host;
        }
        $host_infos = get_host_info($host, $show_vms, $pool, $show_used_values);
        $pool_hosts[] = $host_infos['infos'];
        $pool_cpu_used += $host_infos['cpu_used'];
        $pool_ram_used += $host_infos['ram_used'];
        $pool_disk_used += $host_infos['disk_used'];
    }

    if ($show_vms)
    {
        $pool_halted_vms = get_halted_vms($pool);
    }

    /**************************\
     *     Storage Repository   *
     \**************************/
    $pool_srs = [];
    foreach ($pool['srs'] as $sr)
    {
        if ($sr['size'] != -1 && $sr['size'] != 0)
        {
            $pool_srs[] = get_sr_infos($sr, $show_used_values);
        }
    }

    /**************************\
     *      Format Pool Row     *
     \**************************/
    $tbl_pool_info = new Console_Table(CONSOLE_TABLE_ALIGN_LEFT, '');
    $tbl_pool_info->addRow( [ 'Used ressources' ] );
    $tbl_pool_info->addRow( [ 'CPU', '=', pretty_print($pool_cpu_used, 0, FALSE) ] );
    $tbl_pool_info->addRow( [ 'Ram', '=', pretty_print($pool_ram_used) ] );
    $tbl_pool_info->addRow( [ 'Disk', '=', pretty_print($pool_disk_used, 3) ] );

    $tbl_hosts = new Console_Table(CONSOLE_TABLE_ALIGN_LEFT, '', 1);
    foreach ($pool_hosts as $pool_host)
    {
        $host = [
            $pool_host['name'],
            $pool_host['cpu'],
            $pool_host['mem'],
            ];

        if ($show_vms)
        {
            $tbl_vms = new Console_Table(CONSOLE_TABLE_ALIGN_LEFT);
            if (count($pool_host['vms']) == 0)
            {
                $tbl_vms->addData( [ [ 'no vm running on this host' ] ] );
            }
            else
            {
                $tbl_vms->addData($pool_host['vms']);
                $tbl_vms->addSeparator();
                $tbl_vms->addRow($pool_host['vm_total']);
            }

            $host[] = chop($tbl_vms->getTable());
        }

        $tbl_hosts->addRow($host);
    }

    if ($show_vms && count($pool_halted_vms) > 0)
    {
        $tbl_halted_vms = new Console_Table(CONSOLE_TABLE_ALIGN_LEFT);
        $tbl_halted_vms->addData($pool_halted_vms);
        $tbl_hosts->addRow( [ '', '', '', "\n".'HALTED VM'."\n".chop($tbl_halted_vms->getTable()) ]);
    }

    $tbl_srs_border = '';
    if ($show_vms)
    {
        $tbl_srs_border = CONSOLE_TABLE_BORDER_ASCII;
    }
    $tbl_srs = new Console_Table(CONSOLE_TABLE_ALIGN_LEFT, $tbl_srs_border);
    $tbl_srs->setHeaders( [ 'SR', $header_used_or_available_txt.' / total' ] );
    $tbl_srs->addData($pool_srs);

    $cell_pool_infos = $pool['name_label'].' ('.($master_host ? $master_host['name_label'] : '?NO_MASTER?').")\n".$pool['name_description']."\n\n".chop($tbl_pool_info->getTable());
    if ($show_vms)
    {
        $cell_pool_infos = $cell_pool_infos."\n\n".chop($tbl_srs->getTable());
    }

    $pool_row = [
        $cell_pool_infos,
        chop($tbl_hosts->getTable())
        ];

    if (!$show_vms)
    {
        $pool_row[] = chop($tbl_srs->getTable());
    }

    $pool_rows[] = $pool_row;
}


$tbl = new Console_Table();
$tbl_headers = [ 'Pool Name (description)', 'Host CPU & RAM '.$header_used_or_available_txt ];
if (!$show_vms)
{
    $tbl_headers[] = 'SR '.$header_used_or_available_txt;
}
$tbl->setHeaders($tbl_headers);

$itx_pool = 0;
foreach ($pool_rows as $pool_row)
{
    $tbl->addRow($pool_row);
    if ($itx_pool != count($pools) - 1)
    {
        $tbl->addSeparator();
    }
    $itx_pool++;
}

echo $tbl->getTable();
