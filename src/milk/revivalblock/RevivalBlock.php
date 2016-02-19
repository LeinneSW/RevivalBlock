<?php

namespace milk\revivalblock;

use pocketmine\item\ItemBlock;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;

class RevivalBlock extends PluginBase implements Listener{

    public $pos = [];

    public $conf = [];
    public $rand = [];
    public $revi = [];

    public function onEnable(){
        $this->saveDefaultConfig();
        $this->saveResource("data.yml", false);

        $this->conf = $this->getConfig()->getAll();
        $this->rand = (new Config($this->getDataFolder() . "data.yml", Config::YAML))->getAll();
        $this->revi = (new Config($this->getDataFolder() . "revi.dat", Config::YAML))->getAll();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getLogger()->info(TextFormat::GOLD . "[RevivalBlock]Plugin has been enabled");
    }

    public function onDisable(){
        $data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
        $data->setAll($this->rand);
        $data->save();

        $revi = new Config($this->getDataFolder() . "revi.dat", Config::YAML);
        $revi->setAll($this->revi);
        $revi->save();
        $this->getServer()->getLogger()->info(TextFormat::GOLD . "[RevivalBlock]Plugin has been disabled");
    }

    public function getRevivalBlock(Vector3 $pos){
        return isset($this->revi["{$pos->x}:{$pos->y}:{$pos->z}"]) ? $this->revi["{$pos->x}:{$pos->y}:{$pos->z}"] : false;
    }

    public function PlayerTouchBlock(PlayerInteractEvent $ev){
        $t = $ev->getBlock();
        $p = $ev->getPlayer();
        if($ev->getItem()->getId() == $this->conf["tool-id"] && $p->isOp()){
            if($ev->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK && $ev->getFace() !== 255) {
                $this->pos[$p->getName()]['pos2'] = [$t->x, $t->y, $t->z];
                $p->sendMessage("[RevivalBlock]Pos2지점을 선택했습니다({$t->x}, {$t->y}, {$t->z})");
            }elseif($ev->getAction() == PlayerInteractEvent::LEFT_CLICK_BLOCK){
                $this->pos[$p->getName()]['pos1'] = [$t->x, $t->y, $t->z];
                $p->sendMessage("[RevivalBlock]Pos1지점을 선택했습니다({$t->x}, {$t->y}, {$t->z})");
            }
            $ev->setCancelled();
        }
    }

    public function PlayerBreakBlock(BlockBreakEvent $ev){
        $i = $ev->getItem();
        $t = $ev->getBlock();
        $p = $ev->getPlayer();
        $x = $t->x;
        $y = $t->y;
        $z = $t->z;
        if($i->getId() == $this->conf["tool-id"] && $p->isOp()){
            $this->pos[$p->getName()]['pos1'] = [$x, $y, $z];
            $p->sendMessage("[RevivalBlock]Pos1지점을 선택했습니다($x, $y, $z)");
            $ev->setCancelled();
        }elseif(($value = self::getRevivalBlock($t)) !== false){
            if($value === true){
                $as = explode("/", $this->rand["normal"]);
                if(mt_rand(1, $as[1]) > $as[0]){
                    $ev->setCancelled();
                    return;
                }
                foreach($t->getDrops($i) as $d) $p->getInventory()->addItem(Item::get(...$d));
            }else{
                $block = Item::fromString($value);
                if($t->getId() === $block->getId() and $t->getDamage() === $block->getDamage()){
                    $item = Item::get(Item::AIR);
                    foreach($this->rand[$block->getId()] as $string => $as){
                        $as = explode("/", $as);
                        if(mt_rand(1, $as[1]) <= $as[0]){
                            if(Item::fromString($string) instanceof ItemBlock){
                                $item = Item::fromString($string);
                            }else{
                                unset($this->rand[$block->getId()][$string]);
                            }
                        }
                    }
                    if($item->getId() > 0){
                        $p->getLevel()->setBlock(new Vector3($x,$y,$z), $item->getBlock(), true);
                    }else{
                        foreach($block->getBlock()->getDrops($i) as $drops){
                            $p->getInventory()->addItem(Item::get(...$drops));
                        }
                    }
                }else{
                    foreach ($t->getDrops($i) as $drops){
                        $p->getInventory()->addItem(Item::get(...$drops));
                    }
                    $p->getLevel()->setBlock($t, $block->getBlock(), true);
                }
            }
            $slot = $p->getInventory()->getItemInHand();
            if($slot->isTool() && !$p->isCreative()){
                if($slot->useOn($t) and $slot->getDamage() >= $slot->getMaxDurability()) $slot->count--;
                $p->getInventory()->setItemInHand($slot);
            }
            $ev->setCancelled();
        }
    }

    public function makeBlock($startX, $startY, $startZ, $endX, $endY, $endZ, $isChange, Level $level){
        for($x = $startX; $x <= $endX; $x++){
            for($y = $startY; $y <= $endY; $y++){
                for($z = $startZ; $z <= $endZ; $z++){
                    if($isChange && isset($this->rand[$id = $level->getBlock(new Vector3($x, $y, $z))->getId()])){
                        $this->revi["$x:$y:$z"] = $id;
                    }else{
                        $this->revi["$x:$y:$z"] = true;
                    }
                }
            }
        }
    }

    public function destroyBlock($startX, $startY, $startZ, $endX, $endY, $endZ){
        for($x = $startX; $x <= $endX; $x++){
            for($y = $startY; $y <= $endY; $y++){
                for($z = $startZ; $z <= $endZ; $z++){
                    unset($this->revi["$x:$y:$z"]);
                }
            }
        }
    }

    public function onCommand(CommandSender $i, Command $cmd, $label, array $sub){
        if(!$i instanceof Player) return true;
        $pu = $i->getName();
        $output = "[RevivalBlock]";
        if(!isset($this->pos[$pu]['pos1']) or !isset($this->pos[$pu]['pos2'])){
            $output .= "Please tap a block to make to revival block";
            $i->sendMessage($output);
            return true;
        }
        $sx = min($this->pos[$pu]['pos1'][0], $this->pos[$pu]['pos2'][0]);
        $sy = min($this->pos[$pu]['pos1'][1], $this->pos[$pu]['pos2'][1]);
        $sz = min($this->pos[$pu]['pos1'][2], $this->pos[$pu]['pos2'][2]);
        $ex = max($this->pos[$pu]['pos1'][0], $this->pos[$pu]['pos2'][0]);
        $ey = max($this->pos[$pu]['pos1'][1], $this->pos[$pu]['pos2'][1]);
        $ez = max($this->pos[$pu]['pos1'][2], $this->pos[$pu]['pos2'][2]);
        if($cmd->getName() == "revi"){
            $this->makeBlock($sx, $sy, $sz, $ex, $ey, $ez, isset($sub[0]), $i->getLevel());
        }else{
            $this->destroyBlock($sx, $sy, $sz, $ex, $ey, $ez);
        }
        $output .= $cmd->getName() == "revi" ? "The chosen block was made to revival block" : "The chosen block is no more revival block";
        $i->sendMessage($output);
        return true;
    }
}
