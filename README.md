# 关于

这是解析中国省市区行政区划数据的一个类, 以及处理好的近几年的数据. 

> 数据来源: http://www.mca.gov.cn/article/sj/tjbz/a/


# 需求

PHP5.3+既可


# 示例

```php
$region = new Region();
$sql = $region->parse();
file_put_contents('region_2017_1.sql', $sql);
```
