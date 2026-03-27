/root/cpaneldirect/provirted.phar vnc setup --virt=docker {if $vps_vzid == "0"}{$vps_id}{else}{$vps_vzid|escapeshellarg}{/if} {$param|escapeshellarg};
