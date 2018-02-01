<?php

return [
    'md5 title' => 'File Fingerprint',
    'file' => 'File',
    'file placeholder' => "Project's Relative Path：backend/web/index.php",
    'file project' => 'Project name',
    'project' => 'Project',
    'file md5' => 'View file md5',
    'error todo' => 'Please contact SA or redeployment',
    'error title' => 'deploy error:（',
    'done' => 'Deployment done: )',
    'done praise' => 'Well done：）',
    'version' => 'version：',
    'deploy' => 'Deploy',
    'return' => 'Return',
    'process_detect' => 'Permissions, Directories.',
    'process_pre-deploy' => 'pre-deploy Task.',
    'process_checkout' => 'checkout code.',
    'process_post-deploy' => 'post-deploy Task.',
    'process_rsync' => 'Synchronizing.',
    'process_update' => 'Update(pre-release、switch version、post-release)',
    'deploying' => 'Deploy',

    'deployment id is empty' => 'deployment id is empty：）',
    'deployment id not exists' => 'deployment id not exists：）',
    'deployment only done for once' => 'deployment only be done for once：）',
    'init deployment workspace error' => 'init deployment workspace error',
    'update code error' => 'update code error',
    'pre deploy task error' => 'pre_deploy task error',
    'post deploy task error' => 'post_deploy task error',
    'rsync error' => 'rsync files to remote servers error',
    'package error' => 'package files error',
    'unpackage error' => 'unpackage files error',
    'project configuration works' => 'project configuration works, congratulation：）',
    'update servers error' => 'update the remote servers error',
    'ssh-key to git' => 'add php process user {user} ssh-key to github/gitlab\'s deploy-keys',
    'correct username passwd' => 'svn username and password is correct',
    'hosted server is not writable error' => 'hosted server error: please make sure the user {user} of php process have the access permission of {path}, and {ssh_passwd}.<br>',
    'hosted server ssh error' => 'hosted server error: please make sure {ssh_passwd}.<br>',
    'hosted server sys error' => 'error happens when detecting on hosted server：{error}<br>',
    'hosted server ansible error' => 'hosted server error: please install ansible on hosted server or disable ansible in current project.<br>',
    'target server ssh error' => 'target server error: please make sure the ssh-key of user {local_user} of php process is added to target servers\'s user {remote_user}\'s authorized_keys.<br>',
    'target server is not writable error' => 'target server error: please make sure {remote_user} have the access permission of {path} on target servers.<br>',
    'target server sys error' => 'error happens when detecting on target servers：{error}<br>',
    'target server ansible ping error' => 'ansible ping error: please check ~/.ssh/config file and ssh-key',
    'unknown config' => 'unknown configuration',
    'you are not the manager' => 'you are not the manager',
    'get branches failed' => 'get branches failed：',
    'get commit log failed' => 'get commit log failed：',
    'get tags failed' => 'get tags failed：',
    'unknown scm' => 'unknown scm',
    "unknown slb type" => "unKnow slb type",
    'slb switch error' => 'SLB switch error',
    'slb test error'=>'SLB test error',
    'redis connect error'=>'redis connect failed',
    'manual weight error'=>'project manual weight set error',
];
