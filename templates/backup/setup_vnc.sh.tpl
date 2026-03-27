{if $vps_vzid != "0"}
bash /root/cpaneldirect/vps_docker_setup_vnc.sh {$vps_vzid} {$param|escapeshellarg};
{/if}