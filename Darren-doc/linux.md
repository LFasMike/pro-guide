# linux 常用总结



6.16
#### 环境变量 export命令
```shell
export -p #列出全部环境变量

export PATH=$PATH:123...  #临时导入，若永久的，请在bash_file脚本中添加

```

4.11
shell挂起命令  nohup php artisan make:console  start >> log/redis_orders.log 2>&1 &


12.31
shell生产32位随机密码
date | md5sum |cut -c-32
pro
linux在shell中获取文件目录地址、全地址
root_dir=$(cd "$(dirname "$0")";pwd)


 //目录空间占用
linux: du -h --max-depth=1
macos: du -h -d 1

重定向目录文件，移动
mv -f dir1 dir2

利用命令grep在文件中搜索字符串
grep -rni broker.address.family /
 ps -ef | grep nginx

vim 多标签和多窗口
:tabs  显示已打开标签页的列表，并用“>”标识出当前页面，用“+”标识出已更改的页面。
关闭标签页
:tabc  关闭当前标签页。
:tabo  关闭所有的标签页。
切换标签
:tabn  移动到下一个标签页。 gt
:tabp  移动到上一个标签页。 gT
tabf * .txt  允许你在当前目录搜索文件，tabf 
:tabnew file  等价  :tabe file   在新标签页中打开或新建文件file 

ansible使用

/etc/ansible/hosts:
[test]  # test分组
192.168.0.1  ansible_user=xxx  # 远程服务器地址，指定主机用户名

测试：
ansible all -m ping -u xxx



下载包命令
curl -O http://openresty.org/download/drizzle7-2011.07.21.tar.gz
解压包命令
tar -xzvf openresty-1.13.6.2.tar.gz

输出进程号：用命令： （忽略大小写）
ps ax| grep -i 'get_orders_detail'  | grep -v grep | awk '{print $1}'
查看进程数量
ps aux |grep kafka |grep start | wc -l   

查看cpu数量：
cat /proc/cpuinfo| grep "processor"| wc -l
全部杀掉kafka进程
ps aux |grep kafka |grep start |grep -v grep |awk '{print $2}' |xargs kill
查看进程树
pstree -p 2500

查看端口，监听端口
sudo lsof -Pni4 | grep LISTEN | grep php

比较两个目录下的文件（目录比较命令）
diff -r dir1 dir2 
复制目录时，使用-r选项即可递归拷贝，如下：
cp -r dir1 dir2

linux 查找目录或文件
查找目录：find /（查找范围） -name '查找关键字' -type d
查找文件：find /（查找范围） -name 查找关键字 -print
 
 find ~ -iname  "*说明*"

linux 查找某目录下包含关键字内容的文件
grep -rn "test"  /data/reports
find /root/ –type f |xargs grep “www”  (linux)

 –type f : 文件类型是普通文件


lsof -i:5001 
 最后再：./restart.sh

查看端口：
lsof -i:80
按文件大小 查找文件大小
find . -type f -size +50M  -print0 | xargs -0 du -h | sort -nr  
列出所有的端口
netstat -ntlp



查看Linux查看内核版本
cat /proc/versio
uname -a
查看linux版本
lsb_release -a

查看最后倒数50行的日志文件
 tail -n 500 /tmp/kafka_check_logs.log

一次性递归新建目录命令
mkdir -p
 



linux定时任务

编辑： crontab -e   查看 crontab  -l

```shell

#以下是编辑中常用的：
#every 10s
#* * * * * sleep 10; /schdule_every_ten_sec.sh >> /log/schdule_every_ten_sec.log 2>&1
#every min
* * * * * /schedule_every_min.sh >> /log/schedule_every_min.log 2>&1
#every five min
*/5 * * * * /schedule_five_min.sh  >> /log/schedule_five_min.log 2>&1
#every ten min
*/10 * * * * /schedule_ten_min.sh  >> /log/schedule_ten_min.log 2>&1
#every hour
0 * * * * /schedule_every_hour.sh >> /log/schedule_every_hour.log 2>&1
#every day
0 0 * * * /schedule_every_day.sh >> /log/schedule_every_day.log 2>&1
#every 12:00
0 12 * * * /schedule_every_noon.sh >> /log/schedule_every_noon.log 2>&1

```

12.27
shell rm命令：
rm -r :删除目录
rm -f : 删除文件
-i ：执行前做个提醒

12.3
linux中读写 权限 执行 chmod 命令
-rw------- (600)      只有拥有者有读写权限。
-rw-r--r-- (644)      只有拥有者有读写权限；而属组用户和其他用户只有读权限。
-rwx------ (700)     只有拥有者有读、写、执行权限。
-rwxr-xr-x (755)    拥有者有读、写、执行权限；而属组用户和其他用户只有读、执行权限。
-rwx--x--x (711)    拥有者有读、写、执行权限；而属组用户和其他用户只有执行权限。
-rw-rw-rw- (666)   所有用户都有文件读、写权限。
-rwxrwxrwx (777)  所有用户都有读、写、执行权限。


2:检索所有文件中匹配的字符串(find)
我一般使用: grep -nri key_word ./*
grep -i pattern files ：不区分大小写地搜索。默认情况区分大小写， 

grep -l pattern files ：只列出匹配的文件名， 

grep -L pattern files ：列出不匹配的文件名， 

grep -w pattern files ：只匹配整个单词，而不是字符串的一部分（如匹配‘magic’，而不是‘magical’）， 

grep -C number pattern files ：匹配的上下文分别显示[number]行， 

grep pattern1 | pattern2 files ：显示匹配 pattern1 或 pattern2 的行， 

grep pattern1 files | grep pattern2 ：显示既匹配 pattern1 又匹配 pattern2 的行。 



7.31 

查看文件系统
df -h

查看当前目录每个文件夹的情况
du --max-depth=1 -h   /usr/
列出当前文件夹下所有文件对应的大小
du -sh  *
查看磁盘各分区大小
df -h



ps aux 和ps -ef 
两者的输出结果区别不大，但展示风格不同。aux是BSD风格，-ef是System V风格
 
7.24


linux重启命令
reboot



拨号，连接远程主机，带端口号
telnet 39.108.61.252 9092
 
 
 
12.3
linux中读写 权限 执行 chmod 命令
-rw------- (600)      只有拥有者有读写权限。
-rw-r--r-- (644)      只有拥有者有读写权限；而属组用户和其他用户只有读权限。
-rwx------ (700)     只有拥有者有读、写、执行权限。
-rwxr-xr-x (755)    拥有者有读、写、执行权限；而属组用户和其他用户只有读、执行权限。
-rwx--x--x (711)    拥有者有读、写、执行权限；而属组用户和其他用户只有执行权限。
-rw-rw-rw- (666)   所有用户都有文件读、写权限。
-rwxrwxrwx (777)  所有用户都有读、写、执行权限。