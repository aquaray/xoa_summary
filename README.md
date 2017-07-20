# xoa_summary

## Prerequisite packages/commands

* a xo-server (https://xen-orchestra.com/) installed and configured with a user
* xo-cli (https://xen-orchestra.com/docs/xo-cli.html)
* php5
* pear Console_Table
* pear Console_CommandLine

## Summary

It uses Xen Orchestra CLI to create a Xen Server tree view.

```
root@xoa:~# php xoa_summary.php pool1
+-------------------------+----------------------------------------------------------------------------+---------------------------------------------------------+
| Pool Name (description) | Host CPU & RAM available                                                   | SR available                                            |
+-------------------------+----------------------------------------------------------------------------+---------------------------------------------------------+
| pool1 (master1)         |  + master1 (6.5.0)  [**        ]  1 /  8  [********* ]  26.21 /  31.96 GiB |  SR                 available / total                   |
| Gestion Infra Xen       |                                                                            |   master1 Local SR  [***       ]    0.39 /    1.76 TiB  |
|                         |                                                                            |                                                         |
|  Used ressources        |                                                                            |                                                         |
|  CPU   =        7       |                                                                            |                                                         |
|  Ram   =        3 GiB   |                                                                            |                                                         |
|  Disk  =    1.064 TiB   |                                                                            |                                                         |
+-------------------------+----------------------------------------------------------------------------+---------------------------------------------------------+

root@xoa:~# php xoa_summary.php master1 -v
+-------------------------------------------------------------+----------------------------------------------------------------------------------------------------------------------------------------+
| Pool Name (description)                                     | Host CPU & RAM available                                                                                                               |
+-------------------------------------------------------------+----------------------------------------------------------------------------------------------------------------------------------------+
| pool1 (master1)                                             |  + master1 (6.5.0)  [**        ]  1 /  8  [********* ]  26.21 /  31.96 GiB  +-----------------------+---+-------------+-------------+  |
| Gestion Infra Xen                                           |                                                                             | vm1                   | 4 |       1 GiB |       1 TiB |  |
|                                                             |                                                                             | test1                 | 1 |     256 MiB |       8 GiB |  |
|  Used ressources                                            |                                                                             | XOA                   | 2 |       2 GiB |       8 GiB |  |
|  CPU   =        7                                           |                                                                             +-----------------------+---+-------------+-------------+  |
|  Ram   =        3 GiB                                       |                                                                             | Total: 3 runnings vms | 7 |       3 GiB |       1 TiB |  |
|  Disk  =    1.064 TiB                                       |                                                                             +-----------------------+---+-------------+-------------+  |
|                                                             |                                                                                                                                        |
| +--------------------+------------------------------------+ |                                                                                                                                        |
| | SR                 | available / total                  | |                                                                                                                                        |
| +--------------------+------------------------------------+ |                                                                                                                                        |
| |   master1 Local SR | [***       ]    0.39 /    1.76 TiB | |                                                                                                                                        |
| +--------------------+------------------------------------+ |                                                                                                                                        |
+-------------------------------------------------------------+----------------------------------------------------------------------------------------------------------------------------------------+

```

## Installation

```
apt-get install php5-cli
apt-get install php-pear
pear install Console_Table
pear install Console_CommandLine
```

Register the client (xoa_summary) to your xo-server
```
xo-cli --register https://your/xoa/installation your_user the_user_password
```
Add a crontab (it will create/update by default some files in /tmp/)
```
* * * * * php xoa_summary.php --cron
```

Run it
```
php xoa_summary.php
```

## Usage

```
root@xoa:~# php xoa_summary.php --help
Get a XenServer tree view of pool/host/vm/sr.

It uses the Xen-Orchestra xo-cli
(https://xen-orchestra.com/docs/xo-cli.html) command to get information
from XenServer pools. You have to run once xo-cli --register to register
the client to your xo-server.
This script reads data from files: pool.json, host.json, SR.json, VM.json,
VBD.json, VDI.json. To have them up to date, you should add a cron like:
* * * * * php xoa_summary.php --cron

Usage:
  xoa_summary.php [options] [search]

Options:
  -v, --show-vms             show VMs.
  -i                         Instead displays used values, display
                             available values.
  -p PATH, --json-path=PATH  Path to folder containing pool.json,
                             host.json, SR.json, VM.json, VBD.json,
                             VDI.json files.
                             Default is current directory.
                             Exemple to create files :
                             for i in VDI VM SR host pool VBD; do
                                xo-cli --list-objects type=$i >
                                 /tmp/$i.tmp;
                             done;
                             for i in VDI VM SR host pool VBD; do
                                mv /tmp/$i.tmp /tmp/$i.json;
                             done
  --cron                     Usefull when run by cron to create data files.
  --xo-cli=PATH              xo-cli path.
  --hook=hook                hook run after files update.
  -d, --debug                Set debug mode (only usefull with --cron).
  -h, --help                 show this help message and exit
  --version                  show the program version and exit

Arguments:
  search  Limit display to pool where search is found in "pool name", or
          "vm name" or "sr name" or "host name". Search is done
          case-insensitive.
```
