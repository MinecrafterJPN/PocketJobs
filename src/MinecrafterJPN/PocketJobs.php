<?php

namespace MinecrafterJPN;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;

class PocketJobs extends PluginBase implements Listener
{
    /** @var Config */
    private $users;
    /** @var Config */
    private $joblist;

    public function onLoad()
    {
    }

    public function onEnable()
    {
        if (!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder(), 0755, true);
        $this->users = new Config($this->getDataFolder() . "users.yml", Config::YAML);
        $this->joblist = new Config($this->getDataFolder() . "joblist.yml", Config::YAML,
            array(
                'Woodcutter' => array(
                    'break' => array(
                        array(
                            'ID' => Block::WOOD,
                            'meta' => 0,
                            'amount' => 25
                        ),
                        array(
                            'ID' => Block::WOOD,
                            'meta' => 1,
                            'amount' => 25
                        ),
                        array(
                            'ID' => Block::WOOD,
                            'meta' => 2,
                            'amount' => 25
                        ),
                        array(
                            'ID' => Block::WOOD,
                            'meta' => 3,
                            'amount' => 25
                        ),
                    ),
                    'place' => array(
                        array(
                            'ID' => Block::SAPLING,
                            'meta' => 0,
                            'amount' => 1
                        ),
                        array(
                            'ID' => Block::SAPLING,
                            'meta' => 1,
                            'amount' => 1
                        ),
                        array(
                            'ID' => Block::SAPLING,
                            'meta' => 2,
                            'amount' => 1
                        ),
                        array(
                            'ID' => Block::SAPLING,
                            'meta' => 3,
                            'amount' => 1
                        )
                    )
                ),

                'Miner' => array(
                    'break' => array(
                        array(
                            'ID' => Block::STONE,
                            'meta' => 0,
                            'amount' => 3
                        ),
                        array(
                            'ID' => Block::GOLD_ORE,
                            'meta' => 0,
                            'amount' => 25
                        ),
                        array(
                            'ID' => Block::IRON_ORE,
                            'meta' => 0,
                            'amount' => 20
                        ),
                        array(
                            'ID' => Block::LAPIS_ORE,
                            'meta' => 0,
                            'amount' => 17
                        ),
                        array(
                            'ID' => Block::OBSIDIAN,
                            'meta' => 0,
                            'amount' => 9
                        ),
                        array(
                            'ID' => Block::DIAMOND_ORE,
                            'meta' => 0,
                            'amount' => 80
                        ),
                        array(
                            'ID' => Block::REDSTONE_ORE,
                            'meta' => 0,
                            'amount' => 10
                        )
                    )
                )

            ));
        $this->users->save();
        $this->joblist->save();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable()
    {
        $this->users->save();
        $this->joblist->save();
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (!($sender instanceof Player)) {
            $sender->sendMessage("Must be run in the world!");
            return true;
        }

        switch ($command->getName()) {
            case "job":
                $subCommand = strtolower(array_shift($args));
                switch ($subCommand) {
                    case "":
                        $sender->sendMessage("/jobs my");
                        $sender->sendMessage("/jobs browse");
                        $sender->sendMessage("/jobs join <jobname>");
                        $sender->sendMessage("/jobs leave <jobname>");
                        $sender->sendMessage("/jobs info <jobname>");
                        break;

                    case "my":
                        $slot1 = is_null($this->users->get($sender->getName())['slot1']) ? "empty" : $this->users->get($sender->getName())['slot1'];
                        $slot2 = is_null($this->users->get($sender->getName())['slot2']) ? "empty" : $this->users->get($sender->getName())['slot2'];
                        $sender->sendMessage("Slot1: $slot1, Slot2: $slot2");
                        break;

                    case "browse":
                        foreach ($this->joblist->getAll(true) as $job) {
                            $sender->sendMessage($job);
                        }
                        break;

                    case "join":
                        if (is_null($job = array_shift($args))) {
                            $sender->sendMessage("Usage: /jobs join <jobname>");
                            return true;
                        }
                        if ($this->joblist->exists($job)) {
                            $this->joinJob($sender->getName(), $job);
                        } else {
                            $sender->sendMessage("$job not found");
                        }
                        break;

                    case "leave":
                        if (is_null($job = array_shift($args))) {
                            $sender->sendMessage("Usage: /jobs leave <jobname>");
                            return true;
                        }
                        if ($this->joblist->exists($job)) {
                            $this->leaveJob($sender->getName(), $job);
                        } else {
                            $sender->sendMessage("$job not found");
                        }
                        break;

                    case "info":
                        if (is_null($job = array_shift($args))) {
                            $sender->sendMessage("Usage: /jobs info <jobname>");
                            return true;
                        }
                        if ($this->joblist->exists($job)) {
                            $this->infoJob($sender->getName(), $job);
                        } else {
                            $sender->sendMessage("$job not found");
                        }
                        break;
                }
                break;
        }
        return true;
    }

    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $name = $event->getPlayer()->getName();
        if (!$this->users->exists($name)) {
            $this->users->set($name, array('slot1' => null, 'slot2' => null));
            $this->users->save();
        }
    }

    public function onPlayerBreakBlock(BlockBreakEvent $event)
    {
        $this->workCheck("break", $event->getPlayer()->getName(), $event->getBlock()->getId(), $event->getBlock()->getDamage());
    }

    public function onPlayerPlaceBlock(BlockPlaceEvent $event)
    {
        $this->workCheck("place", $event->getPlayer()->getName(), $event->getBlock()->getId(), $event->getBlock()->getDamage());
    }

    private function workCheck($type, $username, $id, $meta)
    {

        foreach ($this->joblist->getAll() as $jobname => $jobinfo) {
            if (isset($jobinfo[$type])) {
                foreach ($jobinfo[$type] as $detail) {
                    if ($detail['ID'] === $id and $detail['meta'] === $meta) {
                        $amount = $detail['amount'];
                        $slot = $this->users->get($username);
                        if ($slot['slot1'] === $jobname || $slot['slot2'] === $jobname) {
                            $this->getServer()->getPluginManager()->getPlugin("PocketMoney")->grantMoney($username, $amount);
                        }
                    }
                }
            }

        }
    }

    private function joinJob($username, $job)
    {
        $slot = $this->users->get($username);
        if ($slot['slot1'] === $job || $slot['slot2'] === $job) {
            $this->getServer()->getPlayer($username)->sendMessage("You have been already part of $job");
            return;
        }
        if (isset($slot['slot1'])) {
            if (isset($slot['slot2'])) {
                $this->getServer()->getPlayer($username)->sendMessage("Your job slot is full");
            } else {
                $this->users->set($username, array(
                    'slot1' => $slot['slot1'],
                    'slot2' => $job
                ));
                $this->users->save();
                $this->getServer()->getPlayer($username)->sendMessage("Set $job to your job slot2");
            }
        } else {
            $this->users->set($username, array(
                'slot1' => $job,
                'slot2' => $slot['slot2']
            ));
            $this->users->save();
            $this->getServer()->getPlayer($username)->sendMessage("Set $job to your job slot1");
        }
    }

    private function leaveJob($username, $job)
    {
        $slot = $this->users->get($username);
        if ($slot['slot1'] === $job){
            $this->users->set($username, array(
                'slot1' => null,
                'slot2' => $slot['slot2']
            ));
            $this->users->save();
            $this->getServer()->getPlayer($username)->sendMessage("Remove $job from your job slot1");
        } elseif ($slot['slot2'] === $job) {
            $this->users->set($username, array(
                'slot1' => $slot['slot1'],
                'slot2' => null
            ));
            $this->users->save();
            $this->getServer()->getPlayer($username)->sendMessage("Remove $job from your job slot2");
        } else {
            $this->getServer()->getPlayer($username)->sendMessage("You are not part of $job");
        }
    }

    private function infoJob($username, $job)
    {
        foreach($this->joblist->getAll(true) as $aJob){
            if($aJob === $job){
                $info = $this->joblist->get($job);
                foreach($info as $type => $detail){
                    foreach($detail as $value){
                        $id = $value['ID'];
                        $meta = $value['meta'];
                        $amount = $value['amount'];
                        $this->getServer()->getPlayer($username)->sendMessage("$type $id:$meta $amount");
                    }
                }
            }
        }
    }
}