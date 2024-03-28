<?php

declare(strict_types=1);

namespace DiamondStrider1\Remark;

use DiamondStrider1\Remark\Command\Arg\Arg;
use DiamondStrider1\Remark\Command\BoundCommand;
use DiamondStrider1\Remark\Command\Cmd;
use DiamondStrider1\Remark\Command\CmdConfig;
use DiamondStrider1\Remark\Command\CommandHintListener;
use DiamondStrider1\Remark\Command\Guard\Guard;
use DiamondStrider1\Remark\Command\HandlerMethod;
use InvalidArgumentException;
use pocketmine\command\CommandMap;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use ReflectionAttribute;
use ReflectionClass;

/**
 * The static utility class responsible for binding
 * handlers to the command map.
 *
 * See `Remark::bind()` for registering handlers.
 */
final class Remark
{
    private static bool $registered = false;

    /**
     * Registers a packet listener to give command tab-completion to players.
     */
    public static function activate(Plugin $plugin): void
    {
        if(!self::$registered){
            $pm = Server::getInstance()->getPluginManager();
            $pm->registerEvents(new CommandHintListener(), $plugin);
            self::$registered = true;
        }
    }

    /**
     * Binds a handler's methods to a command map, by default
     * using the command map attached to the singleton Server instance.
     */
    public static function command(
        Plugin $plugin,
        object $handler,
        ?CommandMap $cm = null
    ): void {
        if (null === $cm) {
            $cm = Server::getInstance()->getCommandMap();
        }

        $boundCommands = [];
        $reflection = new ReflectionClass($handler);

        $configs = $reflection->getAttributes(CmdConfig::class);
        foreach ($configs as $config) {
            $config = $config->newInstance();
            $boundCommands[$config->name()] = new BoundCommand(
                $plugin,
                $config->name(), $config->description(), $config->aliases(),
                $config->permission(),
            );
        }

        $methods = $reflection->getMethods();
        foreach ($methods as $m) {
            $cmdAttrs = $m->getAttributes(Cmd::class);
            if (0 === count($cmdAttrs)) {
                // This method is not a HandlerMethod.
                continue;
            }

            $guards = $m->getAttributes(Guard::class, ReflectionAttribute::IS_INSTANCEOF);
            $guards = array_map(fn ($x) => $x->newInstance(), $guards);
            $args = $m->getAttributes(Arg::class, ReflectionAttribute::IS_INSTANCEOF);
            $args = array_map(fn ($x) => $x->newInstance(), $args);
            $parameters = $m->getParameters();

            if (count($parameters) !== count($args)) {
                $name = $m->getName();
                throw new InvalidArgumentException("There must be the same number of parameters as Arg's for method $name!");
            }

            foreach ($args as $index => $arg) {
                /** @var Arg $arg @phpstan-ignore-next-line */
                $arg->setParameter($parameters[$index]);
            }

            $handlerMethod = new HandlerMethod($handler, $m, $guards, $args);

            foreach ($cmdAttrs as $cmd) {
                $cmd = $cmd->newInstance();
                if (!isset($boundCommands[$cmd->name()])) {
                    $boundCommands[$cmd->name()] = new BoundCommand(
                        $plugin,
                        $cmd->name(), '', [], null,
                    );
                }
                $bound = $boundCommands[$cmd->name()];
                $bound->attach($cmd->subNames(), $handlerMethod);
            }
        }

        $cm->registerAll(mb_strtolower($plugin->getName()), $boundCommands);
    }
}
