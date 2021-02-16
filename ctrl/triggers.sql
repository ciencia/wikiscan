DROP TRIGGER IF EXISTS `wiki_bots_insert`;
DROP TRIGGER IF EXISTS `wiki_bots_delete`;

delimiter //

CREATE TRIGGER `wiki_bots_insert` AFTER INSERT ON `wiki_bots` FOR EACH ROW
  BEGIN
    insert into `global_bots` (user_name, projects) values (new.user_name, 1) on duplicate key update projects=projects+1;
  END;
//

CREATE TRIGGER `wiki_bots_delete` AFTER DELETE ON `wiki_bots` FOR EACH ROW
  BEGIN
    update global_bots set projects=projects-1 where user_name=old.user_name;
    delete from global_bots where user_name=old.user_name and projects=0;
  END;
//
