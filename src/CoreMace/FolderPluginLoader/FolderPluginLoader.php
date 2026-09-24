<?php
namespace CoreMace\FolderPluginLoader;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\event\plugin\PluginEnableEvent;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginLoader;
use pocketmine\Server;
use pocketmine\utils\MainLogger;
use pocketmine\utils\TextFormat;

class FolderPluginLoader implements PluginLoader
{
    private $server;
    public function __construct(Server $server)
    {
        $this->server = $server;
    }
    public function loadPlugin($file)
    {
        if(is_dir($file) and file_exists($file . "/plugin.yml") and file_exists($file . "/src/"))
            {
            $description = $this->getPluginDescription($file);
            if($description instanceof PluginDescription)
                {
                MainLogger::getLogger()->info(TextFormat::LIGHT_PURPLE . "Loading source plugin " . $description->getFullName());
                $dataFolder = dirname($file) . DIRECTORY_SEPARATOR . $description->getName();
                if(file_exists($dataFolder) and !is_dir($dataFolder)) {
                    trigger_error("Projected dataFolder '" . $dataFolder . "' for " . $description->getName() . " exists and is not a directory", E_USER_WARNING);
                    return null;
                }
                $className = $description->getMain();
                $this->server->getLoader()->addPath($file . "/src");
                if(class_exists($className, true))
                    {
                    $plugin = new $className();
                    $this->initPlugin($plugin, $description, $dataFolder, $file);
                    return $plugin;
                }else{
                    trigger_error("Couldn't load source plugin " . $description->getName() . ": main class not found", E_USER_WARNING);
                    return null;
                }
            }
        }
        return null;
    }
    public function getPluginDescription($file)
    {
        if (is_dir($file) and file_exists($file . "/plugin.yml"))
            {
            $yaml = @file_get_contents($file . "/plugin.yml");
            if ($yaml != "") {
                return new PluginDescription($yaml);
            }
        }
        return null;
    }
    public function getPluginFilters()
    {
        return "/[^\\.]/";
    }
    private function initPlugin(PluginBase $plugin, PluginDescription $description,$dataFolder,$file)
    {
        $plugin->init($this, $this->server,$description,$dataFolder,$file);
        $plugin->onLoad();
    }
    public function enablePlugin(Plugin $plugin)
    {
        if($plugin instanceof PluginBase and !$plugin->isEnabled())
            {
            MainLogger::getLogger()->info("Enabling " . $plugin->getDescription()->getFullName());
            $plugin->setEnabled(true);
            Server::getInstance()->getPluginManager()->callEvent(new PluginEnableEvent($plugin));
        }
    }
    public function disablePlugin(Plugin $plugin)
    {
        if($plugin instanceof PluginBase and $plugin->isEnabled())
            {
            MainLogger::getLogger()->info("Disabling " . $plugin->getDescription()->getFullName());
            Server::getInstance()->getPluginManager()->callEvent(new PluginDisableEvent($plugin));
            $plugin->setEnabled(false);
        }
    }
}
