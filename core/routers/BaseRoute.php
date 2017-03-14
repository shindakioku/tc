<?php

namespace core\routers;

use core\exceptions\RouterException\RouterException;
use core\traits\Router\ParseUrl;
use core\traits\Router\RouteHelper;

class BaseRoute
{
    use ParseUrl, RouteHelper;

    /**
     * @var array
     *
     * Все роутеры
     */
    protected static $routers = [];

    /**
     * @var string
     */
    protected $requestMethod = '';

    /**
     * @var array
     */
    protected static $patterns = [
        '{integer}' => '[0-9]+',
        '{string}'  => '[a-zA-Z]+',
        '{any}'     => '[^/]+',
    ];

    /**
     * @param array ...$arguments
     */
    public static function get(...$arguments): void
    {
        self::$routers[] = [
            'method'     => 'get',
            'url'        => static::replaceUrl($arguments[0]),
            'parse_url'  => static::parse($arguments[0]),
            'call'       => $arguments[1],
            'middleware' => ! empty($arguments[2]) && is_array($arguments[2]) ? $arguments[2]['middleware'] : null,
        ];
    }

    /**
     * функция запускается в application и служит стартом для запуска системы роутеров
     */
    public function initRouters(): void
    {
        $this->startRoute();
    }

    /**
     * Метод проверяет на роутеры на совпадение
     */
    public function startRoute()
    {
        $currentUrl = route()->getCurrentUrl();

        foreach (static::$routers as $k => $v) {
            $uri = $this->returnCurrentUrl(
                $this->removeSlashes(
                    $v['url'], $currentUrl
                )
            );

            if (preg_match_all(
                '#^'.$uri.'$#', $currentUrl, $matches, PREG_SET_ORDER
            )) {
                $matches['call'] = $v['call'];
                $matches['middleware'] = $v['middleware'];
                break;
            }
        }

        if ($matches) {
            return $this->initRout($matches);
        } else {
            return $this->initNotFoundRout();
        }
    }

    /**
     * @param $matches
     *
     * Метод запускается в случае если пользователь перешел по правильной ссылке
     *
     * @return mixed
     * @throws RouterException
     */
    private function initRout($matches)
    {
        if ($matches['call'] instanceof \Closure) {
            return call_user_func($matches['call']);
        }

        $call = explode('@', $matches['call']);
        $class = $call[0];
        $method = $call[1];

        if ( ! class_exists($class)) {
            throw new RouterException('Incorrect path to class');
        }

        // если всего один параметр
        if (2 == count($matches[0])) {
            call_user_func([new $class, $method], $matches[0][1]);
        } else {
            unset($matches[0][0]);
            call_user_func_array([new $class, $method], $matches[0]);
        }
    }

    /**
     * 404 - страница не найдена
     */
    private function initNotFoundRout()
    {
        echo '404';
    }
}