<?php declare(strict_types=1);

function exibirAjuda()
{
    echo <<<EOL
 _____             _                 _    
|  __ \           | |               | |   
| |__) |   _ _ __ | |__   ___   ___ | | __
|  _  / | | | '_ \| '_ \ / _ \ / _ \| |/ /
| | \ \ |_| | | | | |_) | (_) | (_) |   < 
|_|  \_\__,_|_| |_|_.__/ \___/ \___/|_|\_\                                         
                                           
Usage:
  command [arguments]

Available commands:
  clean-cache                     Limpa o cache dos providers.
  run <runbook.yaml> <payload>    Executa uma runbook específico.
  help                            Exibe esta mensagem de ajuda.

Examples:
  runbook clear-cache
  runbook run backup.yml "dump-database"
  runbook help

EOL;
}