
# git 基本操作

git init
添加远程仓库地址

git remote add origin https://code.365yf.cc/zhangzhenyu/test.git

git add .
git commit -m "Initial commit"
git checkout -f
git reset --soft HEAD^   将在未push提交的撤销  一次
git push -u origin master  （加-u  项指定一个默认主机，这样后面就可以直接用push）
git push <远程主机名> <本地分支名>:<远程分支名>

使用到git熟悉命令： 
Git global setup
git config --global user.name "darren"
git config --global user.email "darren@iyich.com"

git remote rename origin old-origin
git remote add origin https://code.365yf.cc/zhangzhenyu/test.git
git push -u origin --all

它会在解决冲突后生成一个原来冲突的备份
git config --global mergetool.keepBackup false

查看本地所有分支
git branch  
查看所有分支
git branch -a  
创建分支
git branch new   \\ git checkout -b new  等效
删除本地分支
branch -d old  
删除远程分支
git push origin --delete zzy-test

git remote -v 查看远程版本库信息

重置当前分支
git reset --hard origin/master

当前分支回退版本(回退3个版本 就用HEAD~3 )
git reset --hard HEAD^

git拉取远程分支到本地分支或者创建本地新分支
git checkout origin/remoteName -b localName



git 
仅显示最近的两次更新
git log -p -2
查看日志更清晰
git log --pretty=oneline

查看分支情况
git branch -v
切换到分支name
git checkout -b name

则可以使用-u选项指定一个默认主机，这样后面就可以不加任何参数使用git push
 git push -u origin master

 删除分支 
 git branch -d temp

拉取远程分支到本地分支
 git pull origin dev:Darren

 查看远程仓库
git remote -v
从远程获取最新版本到本地
git fetch origin aaa
比较远程分支和本地分支
 git log -p aaa origin/aaa
合并远程分支到本地
git merge origin/aaa

