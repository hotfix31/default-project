<?php

namespace Deployer;

require 'recipe/symfony4.php';
if (version_compare(Deployer::get()->getConsole()->getVersion(), '7.0.0', '>=')) {
    require 'contrib/yarn.php';
    require 'contrib/rollbar.php';
    require 'contrib/crontab.php';
} elseif (file_exists(__DIR__.'/vendor/deployer/recipes/recipe')) {
    require __DIR__.'/vendor/deployer/recipes/recipe/yarn.php';
    require __DIR__.'/vendor/deployer/recipes/recipe/rollbar.php';
//    require __DIR__.'/vendor/deployer/recipes/recipe/crontab.php';
} else {
    echo 'Please use Deployer version >= 7.0.0 or install recipes with `composer require --dev deployer/recipes`';
    die;
}

set('ssh_type', 'native');
set('ssh_multiplexing', true);
set('application', 'hotfix31/default-project');
set('repository', 'git@git.hotfix.fr:flebarzic/default-project.git');
set('allow_anonymous_stats', false);
set('git_tty', true);

set('writable_mode', 'chmod');
set('writable_chmod_mode', 'u=rwx,og=rx');
set('writable_recursive', true);

add('shared_dirs', []);
set('bin/php', '/usr/local/bin/php');

host('hotfix.fr')
    ->user('root')
    ->forwardAgent(true)
    ->multiplexing(true)
    ->set('deploy_path', '/var/www');

set(
    'clear_paths',
    [
        './README.md',
        './LICENSE',
        './.gitignore',
        './.git',
        './.php_cs',
        './.php_cs.dist',
        './.php-version',
        './package.json',
        './package-lock.json',
        './yarn.lock',
        './yarn-error.log',
        './symfony.lock',
        './phpunit.xml.dist',
        './phpunit.xml',
        './deploy.php',
        './composer.lock',
        // We keep composer.json as it's needed by
        // the Kernel now in Symfony 4
    ]
);

desc('Create version file');
task(
    'deploy:version',
    static function () {
        if (has('release_path')) {
            cd('{{release_path}}');
        }

        $branch = run('git rev-parse --abbrev-ref HEAD');
        $commit = run('git log --pretty="%h" -n1 HEAD');

        $commitDate = new \DateTime(run('git log -n1 --pretty=%ci HEAD'));
        $commitDate->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
        $dateCommit = $commitDate->format('Y-m-d H:i:s');
        $dateProd = date('Y-m-d H:i:s');

        $version = addslashes(json_encode(compact('branch', 'commit', 'dateCommit', 'dateProd')));
        run("echo \"$version\" > {{release_path}}/.version");
    }
);

after('deploy:update_code', 'deploy:version');
before('deploy:symlink', 'database:migrate');
before('deploy:symlink', 'deploy:clear_paths');
after('deploy:ua:update', 'deploy:files_mode');
after('deploy:failed', 'deploy:unlock');
