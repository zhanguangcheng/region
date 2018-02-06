<?php
/**
 * 解析中国省市区行政区划数据的一个类
 * http://www.mca.gov.cn/article/sj/tjbz/a/
 * @author  詹光成 <14712905@qq.com>
 * @date(2017-03-18)
 */
class Region
{
    private $url = 'http://www.mca.gov.cn/article/sj/tjbz/a/2017/20178/201709251028.html';

    private $tabName = 'region';

    // 直辖市
    private $city = array('北京市', '天津市', '上海市', '重庆市');

    public function __construct($url = null)
    {
        $url === null || $this->url = $url;
    }

    public function parse()
    {
        /*
        | id | pid | code | name |
         */
        try {
            $str = $this->get($this->url);
            $list = $this->getList($str);
            $struct = $this->structuring($list);
            $sql = $this->buildSql($struct);
            return $sql;
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setTabName($tabName)
    {
        $this->tabName = $tabName;
    }

    private function get($url)
    {
        return file_get_contents($url);
    }

    private function getList($str)
    {
        $pattern = '/<td class=xl\d+>(\d+)<\/td>\n*\s*<td class=xl\d+>(.+?)<\/td>/';
        if (!preg_match_all($pattern, $str, $arr)) {
            throw new Exception('正则匹配失败');
        }

        // $result: ['code'=>110000, 'name'=>'北京市']
        $result = array();
        for ($i=0; $i < count($arr[1]); $i++) { 
            $result[] = array('code' => $arr[1][$i], 'name' => $this->stripTags($arr[2][$i]));
        }
        return $result;
    }

    private function stripTags($str)
    {
        return trim(strtr($str, array(
            "<span style='mso-spacerun:yes'>" => '',
            "</span>" => '',
        )), '  ');
    }

    /**
     * 结构化城市数据
     * @param  array $list 列表数据
     * @return array       ['code'=>, 'name'=>'', 'sub'=> ['code', 'name'=>'', sub'=>[...]]]
     */
    private function structuring($list)
    {
        if (empty($list)) {
            throw new Exception('列表为空');
        }
        // 省份
        $province = array_filter($list, function($v) {
            return $v['code'] % 10000 === 0;
        });
        
        // 城市 & 地区
        foreach ($province as &$pro) {
            if (in_array($pro['name'], $this->city)) {
                $citys = array($pro);
                $citys[0]['code'] += 100;
                $area = array_filter($list, function ($v) use($pro) {
                    return !strncmp($v['code'], $pro['code'], 2)
                        && $v['code'] % 10000 !== 0;
                });
                $citys[0]['sub'] = $area;
            } else {
                $citys = array_filter($list, function($v) use($pro) {
                    return !strncmp($v['code'], $pro['code'], 2)
                        // 省直辖的县级行政单位第3,4位是90开始的，县级市就从9001，各县就从9021开始排。
                        && ($v['code'] % 100 === 0 || substr($v['code'], 2, 2) === '90')
                        && $v['code'] % 10000 !== 0;
                });
                foreach ($citys as &$city) {
                    $area = array_filter($list, function($v) use($city) {
                        return !strncmp($v['code'], $city['code'], 4)
                            && substr($city['code'], 2, 2) !== '90'
                            && $v['code'] % 100 !== 0;
                    });
                    $city['sub'] = $area;
                }
            }
            $pro['sub'] = $citys;
        }
        return $province;
    }

    /**
     * 构建sql
     */
    private function buildSql($arr)
    {
        if (empty($arr)) {
            throw new Exception('城市数据为空');
        }
        $data = array();
        $id = 0;
        foreach ($arr as $pro) {
            $pid = ++$id;
            $data[] = array(
                'id' => $pid,
                'pid' => 0,
                'code' => $pro['code'],
                'name' => $pro['name'],
            );
            foreach ($pro['sub'] as $city) {
                $pid2 = ++$id;
                $data[] = array(
                    'id' => $pid2,
                    'pid' => $pid,
                    'code' => $city['code'],
                    'name' => $city['name'],
                );
                foreach ($city['sub'] as $area) {
                    $data[] = array(
                        'id' => ++$id,
                        'pid' => $pid2,
                        'code' => $area['code'],
                        'name' => $area['name'],
                    );
                }
            }
        }

        $sql = "CREATE TABLE IF NOT EXISTS `$this->tabName`(\n    `id` SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n    `pid` SMALLINT UNSIGNED NOT NULL,\n    `code` MEDIUMINT UNSIGNED NOT NULL,\n    `name` VARCHAR(20) NOT NULL,\n    INDEX `pid`(`pid`)\n) engine=innodb default charset=utf8;\n\n";
        $len = count($data);
        foreach ($data as $k => $v) {
            if ($k % 2000 == 0) {
                $sql .= "INSERT INTO {$this->tabName} (id,pid,code,name) VALUES\n";
            }
            if (($k + 1) % 2000 == 0 || $len == $k+1) {
                $sql .= "({$v['id']},{$v['pid']},{$v['code']},'{$v['name']}');\n\n";
            } else {
                $sql .= "({$v['id']},{$v['pid']},{$v['code']},'{$v['name']}'),\n";
            }
        }
        return $sql;
    }

}
