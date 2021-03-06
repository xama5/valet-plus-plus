<?php

namespace Valet;

use Exception;
use ValetDriver;

class DevTools
{
    const WP_CLI_TOOL = 'wp-cli';
    const PV_TOOL = 'pv';
    const GEOIP_TOOL = 'geoip';


    const SUPPORTED_TOOLS = [
        self::WP_CLI_TOOL,
        self::PV_TOOL,
        self::GEOIP_TOOL
    ];

    var $brew;
    var $cli;
    var $files;
    var $configuration;
    var $site;
    var $mysql;

    /**
     * Create a new Nginx instance.
     *
     * @param  Brew $brew
     * @param  CommandLine $cli
     * @param  Filesystem $files
     * @param  Configuration $configuration
     * @param  Site $site
     * @param Mysql $mysql
     */
    function __construct(Brew $brew, CommandLine $cli, Filesystem $files,
                         Configuration $configuration, Site $site, Mysql $mysql)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->site = $site;
        $this->files = $files;
        $this->configuration = $configuration;
        $this->mysql = $mysql;
    }

    /**
     * Install development tools using brew.
     *
     * @return void
     */
    function install()
    {
        info('[devtools] Installing tools');

        foreach (self::SUPPORTED_TOOLS as $tool) {
            if ($this->brew->installed($tool)) {
                info('[devtools] ' . $tool . ' already installed');
            } else {
                $this->brew->ensureInstalled($tool, []);
            }
        }
    }

    /**
     * Uninstall development tools using brew.
     *
     * @return void
     */
    function uninstall()
    {
        info('[devtools] Uninstalling tools');

        foreach (self::SUPPORTED_TOOLS as $tool) {
            if (!$this->brew->installed($tool)) {
                info('[devtools] ' . $tool . ' already uninstalled');
            } else {
                $this->brew->ensureUninstalled($tool, ['--force']);
            }
        }
    }

    function sshkey()
    {
        $command = null;
        if (PHP_OS === 'Darwin') {
            $command = 'pbcopy';
        } else if (PHP_OS === 'Linux' && exec('which sxel') !== '') {
            $command = 'xsel --clipboard --input';
        } else if (PHP_OS === 'Linux' && exec('which xclip') !== '') {
            $command = 'xclip -selection clipboard';
        } else {
            throw new Exception('No suitable method found to copy the ssh key on your system. Linux users should install either xsel or xclip');
        }

        $this->cli->passthru($command . ' < ~/.ssh/id_rsa.pub');
        info('Copied ssh key to your clipboard');
    }

    function phpstorm()
    {
        info('Opening PHPstorm');

        $this->cli->runAsUser('open -a PhpStorm ./');
    }

    function sourcetree()
    {
        info('Opening SourceTree');
        $this->cli->runAsUser('open -a SourceTree ./');
    }

    function vscode()
    {
        info('Opening Visual Studio Code');

        $names = array('code', 'vscode');

        foreach ($names as $name) {
            $command = exec("which $name");

            if ($command !== '') {
                break;
            }
        }

        if (!$command) {
            throw new Exception('vscode not found. Please install it.');
        }

        $output = $this->cli->runAsUser($command . ' $(git rev-parse --show-toplevel)');

        if (strpos($output, 'fatal: Not a git repository') !== false) {
            throw new Exception('Could not find git directory');
        }
    }

    function tower()
    {
        if (PHP_OS !== 'Darwin') {
            throw new Exception('This command is only supported on macOS');
        }

        info('Opening git tower');
        if (!$this->files->exists('/Applications/Tower.app/Contents/MacOS/gittower')) {
            throw new Exception('gittower command not found. Please install gittower by following the instructions provided here: https://www.git-tower.com/help/mac/integration/cli-tool');
        }

        $output = $this->cli->runAsUser('/Applications/Tower.app/Contents/MacOS/gittower $(git rev-parse --show-toplevel)');

        if (strpos($output, 'fatal: Not a git repository') !== false) {
            throw new Exception('Could not find git directory');
        }
    }

    function configure()
    {
        require realpath(__DIR__ . '/../drivers/require.php');

        $driver = ValetDriver::assign(getcwd(), basename(getcwd()), '/');

        $secured = $this->site->secured();
        $domain = $this->site->host(getcwd()) . '.' . $this->configuration->read()['domain'];
        $isSecure = in_array($domain, $secured);
        $url = ($isSecure ? 'https://' : 'http://') . $domain;

        if (method_exists($driver, 'configure')) {
            return $driver->configure($this, $url);
        }

        info('No configuration settings found.');
    }
}
