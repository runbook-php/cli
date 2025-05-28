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
  vault key:generate              Cria uma nova secret key para o cofre.
  vault secret:put                Cria um novo secret no cofre.
  vault secret:destroy            Hard delete da secret.
  vault secret:all                Exibe os path secrets cadastrados no cofre.
  help                            Exibe esta mensagem de ajuda.

Examples:
  runbook clear-cache
  runbook run backup.yml "dump-database"
  runbook vault key:generate
  runbook vault secret:put
  runbook vault secret:destroy
  runbook vault secret:all
  runbook help

EOL;
}