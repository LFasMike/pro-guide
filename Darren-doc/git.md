
# git 基本操作
git init
添加远程仓库地址

git remote add origin https://code.365yf.cc/zhangzhenyu/test.git
git checkout -f
git reset --soft HEAD^   将在未push提交的撤销  一次
重置当前分支
git reset --hard origin/master
当前分支回退版本(回退3个版本 就用HEAD~3 )
git reset --hard HEAD^
清空当前本地所有变更
git clean -df 
则可以使用-u选项指定一个默认主机，这样后面就可以不加任何参数使用git push
git push -u origin master
git push <远程主机名> <本地分支名>:<远程分支名>
git -f push 强推版本覆盖远程

使用到git熟悉命令： 
git config --global user.name "darren"
git config --global user.email "darren@iyich.com"
git config --list  查看当前配置情况
git remote rename origin old-origin
git remote add origin https://code.365yf.cc/zhangzhenyu/test.git
git remote -v 查看远程版本库信息

它会在解决冲突后生成一个原来冲突的备份
git config --global mergetool.keepBackup false

查看本地所有分支
git branch  -vv
查看所有分支 和信息
git branch -va 
查看远程所有分支 
git branch -r 
创建分支
git branch new   \\ git checkout -b new  等效
删除本地分支
branch -D old  
删除远程分支 先删除本地该分支，在覆盖远程
git branch -r -d origin/branch-name  

git拉取远程分支到本地分支或者创建本地新分支
git checkout origin/remoteName -b localName
或者：git checkout -b new origin/new

git撤销本地所有为更改的提交
git clean -df

仅显示最近的两次更新
git log -p -2
查看历史提交日志 
git log --pretty=oneline
查看每一次提交的详细内容
git log --stat --abbrev-commit

拉取远程分支到本地分支
 git pull origin dev:Darren
 拉取最新，并归到当前提交的后面
 git pull --rebase 

从远程获取最新版本到本地
git fetch origin aaa
比较远程分支和本地分支
 git log -p aaa origin/aaa
合并远程分支到本地
git merge origin/aaa

如果显示拒绝合并和提交时： 在你操作命令后面加--allow-unrelated-histories
eg:  git merge master --allow-unrelated-histories

暂存功能
git stash 将当前所有修改项(未提交的)暂存，压栈。此时代码回到你上一次的提交
git stash list将列出所有暂存项。
git stash clear 清除所有暂存项。
git stash apply 将暂存的修改重新应用，