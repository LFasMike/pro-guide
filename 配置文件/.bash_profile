source ~/.profile

source /Users/darren/projects/neutron/lib/completion.bash

[[ -s "$HOME/.profile" ]] && source "$HOME/.profile" # Load the default .profile

[[ -s "$HOME/.rvm/scripts/rvm" ]] && source "$HOME/.rvm/scripts/rvm" # Load RVM into a shell session *as a function*


###############goland
export GO111MODULE=off
export GOROOT=/usr/local/go
export GOPATH=/Users/darren/go
export PATH=$PATH:/Users/darren/go/src/github.com/beego/bee/
export PATH=$PATH:$GOPATH/bin
export DEPLOY_PATH=/Users/darren/go/src/deploy


#自定义快捷命令
alias ppsql='psql -U postgres'
alias rrm='rm -rf '
alias sss_bash_profile='source ~/.bash_profile'
alias pps='ps -ef |grep '
alias tf='tail -f '
alias lll='ls -all'
alias ll='ls -lh'
alias kk='ls -all'
alias cc='clear'
alias ls='ls -G'
alias vimbash_pro='vim ~/.bash_profile'
alias cdnginx= 'cd /usr/local/nginx/conf'
alias varlog='cd /usr/local/var/log'
alias mamplog='/Applications/MAMP/logs'
alias guide='~/projects/guide'
alias ppp='~/projects'
alias 查的='cd '

#捞月狗项目
alias baolei='/Users/darren/projects/neutron '
alias lyg_ceshi='ssh land@118.178.128.61'
alias lyg_order_prod_huidu='ssh land@172.16.165.35' #./jumpto lyg_php_service_04

alias tasklyg='ssh land@172.16.163.251'
alias log_lyg='./jumpto vpcplayground_01'
alias order_php_ssh_lyg='./jumpto  vpcplayground_10'
alias play_admin_lyg_ssh='ssh land@172.16.163.255'  #./jumpto peiwan_om_test
alias lygapp='/Users/darren/projects/lyg_app'
alias hui-lyg='ssh land@172.16.34.15' #phpgray

#go服务器
alias go-test='ssh land@114.55.111.198'
alias go-stag1='ssh land@172.16.164.179'
alias go-stag2='ssh land@172.16.164.180'
alias go-stag3='ssh land@172.16.164.181'
alias go-vender='/Users/darren/go/src/vendor/laoyuegou.pb'
alias go-src='cd /Users/darren/go/src'


alias order-start='php -S 127.0.0.1:9988 -t /Users/darren/projects/order_php/public >> /usr/local/var/log/php7.log 2>&1 &'
alias god-start='php -S 127.0.0.1:9998 -t /Users/darren/projects/god_php/public >> /usr/local/var/log/php7.log 2>&1 &'


alias redis_go_test='redis-cli -h r-bp1c567a657a6e14.redis.rds.aliyuncs.com -a LOYOGOU2016redis'
alias redis_app_prod='redis-cli -h a5c33907a2e04ca3424.redis.rds.aliyuncs.com -a a5c33907a2e04ca3:LOYOGOU2015redis' #app仓库 的user·库
alias redis_go_pro_play='redis-cli -h r-bp186cf3f5f82ae4826.redis.rds.aliyuncs.com -a LOYOGOU2016redis' #聊天室和直播
alias redis_go_product_chatroom='redis-cli -h r-bp186cf3f5f82ae4826.redis.rds.aliyuncs.com -p 6379 -a LOYOGOU2016redis'
alias redis_order_golang_test='redis-cli -h r-bp1c567a657a6e14.redis.rds.aliyuncs.com -a LOYOGOU2016redis'
alias redis_order_golang_staging='redis-cli -h r-bp107fc21c97e564.redis.rds.aliyuncs.com -a LOYOGOU2016redis'
alias redis_go_pro_order='redis-cli -h r-bp1d3973083dd134304.redis.rds.aliyuncs.com -a LOYOGOU2016redis' #大神和订单都在这里


#ssh快速链接
alias sshtengxun='ssh root@58.87.71.104'
alias sshxiangce='ssh root@47.100.56.225'
alias sshxihe='ssh root@47.52.75.114'
alias sshlivaway='ssh superadmin@47.96.231.54'
alias sshali='ssh root@47.74.230.69'
alias sshengine='ssh root@45.32.42.197'


#项目使用快捷操作
alias ppa='php artisan '
alias ppt='php artisan tinker '
alias redis-local='redis-server /Users/darren/src/redis-5/redis.conf' #暂用自带配置6379


export PATH=$PATH:/usr/local/sbin
export PATH=/usr/local/openresty/nginx/sbin:$PATH
export PATH=/Users/darren/src/kafka2.11-1.1.1/bin:$PATH
export PATH=$PATH:/usr/local/bin
export PATH=$PATH:/Users/darren/src/apache-maven-3.5.4/bin  #maven的路径
export PATH=$PATH:~/src/apache-tomcat-8.5.34/bin 
export PATH=$PATH:/usr/local/lib/node_modules/eslint/bin
export PATH=$PATH:/usr/local/mysql/bin
export PATH=$PATH:/Users/darren/src/icomet-master
export PATH=$PATH:/usr/local/nginx/sbin
export PATH=$PATH:/usr/local/ssdb
export PATH=$PATH:/Users/darren/src/redis-5/src
export DISPLAY=:0
 

#循环遍历当前目录中脚本文件
for file in ~/.{path,bash_prompt,exports,aliases,functions,extra}; do
 [ -r "$file" ] && source "$file"
done
unset file


#终端代理配置 开启后每次打开终端都生效
function proxy_off(){
    unset http_proxy
    unset https_proxy
    echo -e "已关闭代理"
}

function proxy_on() {
    export no_proxy="localhost,127.0.0.1,localaddress,.localdomain.com"
    export http_proxy="http://127.0.0.1:1088"
    export https_proxy=$http_proxy
    echo -e "已开启代理"
}

function sshh(){
#遍历添加用户登录时添加本地秘钥
for file in `ls /Users/darren/.ssh/id_rs* |grep -v pub`
do
 [ -r "$file" ] && ssh-add "$file"
done
unset file
}

#/Users/darren/.oh-my-zsh/themes/robbyrussell.zsh-theme 这里是直接篡改主题显示的配置 以下任意终端都生效：
PROMPT='${ret_status}%{$fg[cyan]%}%d$(git_prompt_info)'
ZSH_THEME_GIT_PROMPT_PREFIX="%{$fg_bold[blue]%}(%{$fg[red]%}"
ZSH_THEME_GIT_PROMPT_SUFFIX="%{$reset_color%}"
ZSH_THEME_GIT_PROMPT_DIRTY="%{$fg[blue]%})%{$fg[yellow]%}✗"
ZSH_THEME_GIT_PROMPT_CLEAN="%{$fg[blue]%})"

