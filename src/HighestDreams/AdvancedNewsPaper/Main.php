<?php

declare(strict_types=1);

namespace HighestDreams\AdvancedNewsPaper;

use Closure;
use jojoe77777\FormAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Attribute;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener
{

    public static $Database;
    public static $News;
    public static $Settings;
    private $callbacks = [];

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        foreach (['Settings.yml', 'noImage.png'] as $resource) {
            $this->saveResource($resource);
        }
        self::$Database = new Config ($this->getDataFolder() . 'News.json', Config::JSON);
        self::$Settings = new Config ($this->getDataFolder() . 'Settings.yml');
        self::$News = new News ($this);
        $this->getScheduler()->scheduleRepeatingTask(new class extends Task {
            public function onRun(int $currentTick)
            {
                Main::$News::save();
            }
        }, 3600 * 20);
    }

    /**
     * @param CommandSender $player
     * @param Command $cmd
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $player, Command $cmd, string $label, array $args): bool
    {
        if (!$player instanceof Player) return true;
        if (strtolower($cmd->getName()) === "news") {
            if ((empty(self::$Settings->get('worlds')) or (!empty(self::$Settings->get('worlds') and in_array($player->getLevel()->getFolderName(), self::$Settings->get('worlds')))))) {
                $this->NewsMenu($player);
            } else {
                $player->sendMessage('Command is not allowed in this world.');
            }
        }

        return true;
    }

    public function NewsMenu(Player $player)
    {
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $player, $data = null) {
            if ($data === null) {
                return;
            }
            for ($i = 0; $i < 10; $i++) {
                if ($data === $i) {
                    $this->NewsContent($player, $i);
                    break;
                }
            }
        });
        $form->setTitle("§l§4-= §9News§fPaper§4 =-");
        $form->setContent('§cNews §dCountry§4 : §6' . self::$Settings->get('country') . "\n" . "§cNews §dCategory §4: §6" . self::$Settings->get('category') . "\n" . "§cNews §dLanguage§4 : §6" . self::$Settings->get('language'));
        for ($i = 0; $i < 10; $i++) {
            $form->addButton(self::$News::getTitle($i), self::$News::isImageEmpty($i) ? 0 : 1, self::$News::isImageEmpty($i) ? $this->getDataFolder() . 'noImage.png' : self::$News::getImage($i));
        }
        $form->sendToPlayer($player);
        return $form;
    }

    public function NewsContent(Player $player, $i)
    {
        $api = $this->getServer()->getPluginManager()->getPlugin("FormAPI");
        $form = $api->createSimpleForm(function (Player $player, $data = null) {
            if ($data === null) return;
            if ($data === 0) $this->NewsMenu($player);
        });
        $form->setTitle("§l§4-= §9News§fPaper§4 =-");
        $form->setContent('§l§e+ §6Content§c: §b' . self::$News::getContent($i) . "\n\n" . '§dNews§fDate§c : §a' . self::$News::getDetail($i, 'date') . "\n" . '§dNews§fTime §c: §a' . self::$News::getDetail($i, 'time') . "\n" . '§dSou§frce §c: §a' . self::$News::getSource($i) . "\n\n" . '§eRead more §c: §a' . self::$News::readMore($i)); # Some times journalists write content in news description... :L
        $form->addButton('§l§cBack to menu');
        $form->sendToPlayer($player);
        return $form;
    }

    /**
     * Thanks to Muqsit (FormImageFixer)
     * @param DataPacketReceiveEvent $event
     * @priority MONITOR
     * @ignoreCancelled true
     */
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        if ($packet instanceof NetworkStackLatencyPacket && isset($this->callbacks[$id = $event->getPlayer()->getId()][$ts = $packet->timestamp])) {
            $cb = $this->callbacks[$id][$ts];
            unset($this->callbacks[$id][$ts]);
            if (count($this->callbacks[$id]) === 0) {
                unset($this->callbacks[$id]);
            }
            $cb();
        }
    }

    /**
     * Thanks to Muqsit (FormImageFixer)
     * @param DataPacketSendEvent $event
     * @priority MONITOR
     * @ignoreCancelled true
     */
    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        if ($event->getPacket() instanceof ModalFormRequestPacket) {
            $player = $event->getPlayer();
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($player) : void {
                if ($player->isOnline()) {
                    $this->onPacketSend($player, static function () use ($player) : void {
                        if ($player->isOnline()) {
                            $pk = new UpdateAttributesPacket();
                            $pk->entityRuntimeId = $player->getId();
                            $pk->entries[] = $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE_LEVEL);
                            $player->sendDataPacket($pk);
                        }
                    });
                }
            }), 1); # 1? BRUH
        }
    }

    /**
     * Thanks to Muqsit (FormImageFixer)
     * @param Player $player
     * @param Closure $callback
     */
    private function onPacketSend(Player $player, Closure $callback): void
    {
        $ts = mt_rand() * 1000;
        $pk = new NetworkStackLatencyPacket();
        $pk->timestamp = $ts;
        $pk->needResponse = true;
        $player->sendDataPacket($pk);
        $this->callbacks[$player->getId()][$ts] = $callback;
    }

    /**
     * Thanks to Muqsit (FormImageFixer)
     * @param PlayerQuitEvent $event
     * @priority MONITOR
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        unset($this->callbacks[$event->getPlayer()->getId()]);
    }
}
