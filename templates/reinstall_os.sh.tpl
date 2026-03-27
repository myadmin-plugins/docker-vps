/root/cpaneldirect/provirted.phar stop --virt=docker -f {$vps_vzid|escapeshellarg};
/root/cpaneldirect/provirted.phar destroy --virt=docker {$vps_vzid|escapeshellarg};
