<?php
namespace CoreMace;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;
use pocketmine\event\Listener;
use pocketmine\Player;
use CoreMace\FolderPluginLoader\FolderPluginLoader;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\LavaParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\item\Item;
use pocketmine\inventory\ShapedRecipe;

class Main extends PluginBase implements Listener
{
    const MACE_NAME   = "§l§cMACE";
    const MACE_ID     = 271;
    const MACE_DAMAGE = 8;
    const WIND_ID   = 351;
    const WIND_META = 8;
    const WIND_NAME = "§b§lWind Charge";
    public $noFallDamage = [];
    public function onEnable()
    {
        $this->registerFolderPluginLoader();
        $this->registerRecipes();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    private function registerFolderPluginLoader()
    {
        $pluginManager = $this->getServer()->getPluginManager();
        $interface = "CoreMace\\FolderPluginLoader\\FolderPluginLoader";

        $pluginManager->registerInterface($interface);
        $pluginManager->loadPlugins($this->getServer()->getPluginPath(),[$interface]);
        $this->getServer()->enablePlugins(PluginLoadOrder::STARTUP);
    }
    private function isMace($item)
    {
        if($item === null)
            return false;
        if($item->getId() !== self::MACE_ID)
            return false;
        return $item->getCustomName() === self::MACE_NAME;
    }
    private function makeMaceItem($count = 1)
    {
        $item = Item::get(self::MACE_ID,0,$count);
        $item->setCustomName(self::MACE_NAME);
        return $item;
    }
    private function makeWindItem($count = 1)
    {
        $item = Item::get(self::WIND_ID, self::WIND_META, $count);
        $item->setCustomName(self::WIND_NAME);
        return $item;
    }
    private function registerRecipes()
    {
        $craftingManager = $this->getServer()->getCraftingManager();
        $maceRecipe = new ShapedRecipe($this->makeMaceItem(1),
            "ODO",
            "BLB",
            "ODO"
        );
        $maceRecipe->setIngredient("O",Item::get(49,0));
        $maceRecipe->setIngredient("D",Item::get(57,0));
        $maceRecipe->setIngredient("B",Item::get(352,0));
        $maceRecipe->setIngredient("L",Item::get(369,0));
        $craftingManager->registerRecipe($maceRecipe);
        $windRecipe = new ShapedRecipe($this->makeWindItem(1),
            "SIS",
            "IFI",
            "SIS"
        );
        $windRecipe->setIngredient("S",Item::get(332,0));
        $windRecipe->setIngredient("I",Item::get(265,0));
        $windRecipe->setIngredient("F",Item::get(288,0));
        $craftingManager->registerRecipe($windRecipe);
    }
    public function onDamage(EntityDamageEvent $event)
    {
        if($event->getCause() === EntityDamageEvent::CAUSE_FALL)
            {
            $entity = $event->getEntity();
            if($entity instanceof Player && isset($this->noFallDamage[$entity->getName()]))
                {
                $event->setCancelled(true);
                unset($this->noFallDamage[$entity->getName()]);
                return;
            }
        }
        if(!$event instanceof EntityDamageByEntityEvent)
            return;
        $attacker = $event->getDamager();
        $victim   = $event->getEntity();
        if(!$attacker instanceof Player || !$victim instanceof Player)
            return;
        if(!$attacker->isAlive() || !$victim->isAlive())
            return;
        if(!$attacker->isOnline() || !$victim->isOnline())
            return;
        $inv = $attacker->getInventory();
        if($inv === null)
            return;
        $item = $inv->getItemInHand();
        if(!$this->isMace($item))
            return;
        $level = $attacker->getLevel();
        if($level === null)
            return;
        if($attacker->isOnGround())
            return;
        $event->setCancelled(true);
        $newHp = $victim->getHealth() - self::MACE_DAMAGE;
        if($newHp <= 0)
            {
            $victim->setHealth(0);
        }else{
            $victim->setHealth($newHp);
            $this->getServer()->getScheduler()->scheduleDelayedTask(new MaceEffectTask($attacker,$victim,$this),1);
        }
    }
    public function onInteract(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        if(!$player instanceof Player || !$player->isOnline()) return;
        $inv = $player->getInventory();
        if($inv === null)
            return;
        $item = $inv->getItemInHand();
        if($item === null)
            return;
        if($item->getId() !== self::WIND_ID || $item->getDamage() !== self::WIND_META)
            return;
        $level = $player->getLevel();
        if($level === null)
            return;

        $player->setMotion(new Vector3(0,1.2,0));
        $pos = new Vector3($player->x, $player->y, $player->z);
        $level->addParticle(new HugeExplodeParticle($pos));
        $level->addSound(new ExplodeSound($pos));
        $level->addSound(new BlazeShootSound($pos));
        $this->noFallDamage[$player->getName()] = true;
        $item->setCount($item->getCount() - 1);
        $inv->setItemInHand($item);
    }
}
class MaceEffectTask extends Task
{
    private $plugin;
    private $attacker;
    private $victim;
    private $ticks = 0;
    public function __construct(Player $attacker,Player $victim,Main $plugin)
    {
        $this->attacker = $attacker;
        $this->victim   = $victim;
        $this->plugin   = $plugin;
    }
    public function onRun($tick)
    {
        if(
            !$this->attacker instanceof Player ||
            !$this->victim instanceof Player ||
            !$this->attacker->isOnline() ||
            !$this->victim->isOnline() ||
            !$this->attacker->isAlive() ||
            !$this->victim->isAlive()
        )
        {
            return;
        }
        $this->ticks++;
        if($this->ticks > 4)
            return;
        $level = $this->victim->getLevel();
        if($level === null)
            return;
        foreach([$this->victim, $this->attacker] as $p)
            {
            $pos = new Vector3($p->x, $p->y + 1, $p->z);
            for($i = 0; $i < 8; $i++)
                {
                $angle = ($i / 8) * M_PI * 2;
                $level->addParticle(new FlameParticle(new Vector3(
                    $pos->x + cos($angle) * 0.6,
                    $pos->y,
                    $pos->z + sin($angle) * 0.6
                )));
            }
            for($i = 0; $i < 4; $i++)
                {
                $level->addParticle(new LavaParticle(new Vector3(
                    $pos->x + (lcg_value() - 0.5) * 1.2,
                    $pos->y + lcg_value() * 1.2,
                    $pos->z + (lcg_value() - 0.5) * 1.2
                )));
            }
        }
        if($this->ticks === 1)
            {
            $vPos = new Vector3($this->victim->x, $this->victim->y + 1, $this->victim->z);
            $level->addParticle(new HugeExplodeParticle($vPos));
            for($i = 0; $i < 12; $i++)
                {
                $level->addParticle(new RedstoneParticle(new Vector3(
                    $vPos->x + (lcg_value() - 0.5) * 2.0,
                    $vPos->y + lcg_value() * 2.0,
                    $vPos->z + (lcg_value() - 0.5) * 2.0
                )));
            }
            $level->addSound(new ExplodeSound($vPos));
            $level->addSound(new BlazeShootSound($vPos));
            $level->addSound(new AnvilFallSound($vPos));
            $dx  = $this->victim->x - $this->attacker->x;
            $dz  = $this->victim->z - $this->attacker->z;
            $len = sqrt($dx * $dx + $dz * $dz);
            if ($len <= 0) $len = 1;
            $dx /= $len;
            $dz /= $len;
            $this->attacker->setMotion(new Vector3(0, 1.6, 0));
            $this->plugin->noFallDamage[$this->attacker->getName()] = true;
            $this->victim->setMotion(new Vector3($dx * 1.2, 0.1, $dz * 1.2));
        }
    }
}
