CREATE FUNCTION get_doc_category_tree(cat_id BIGINT) RETURNS VARCHAR(255)
BEGIN
  DECLARE cat_name VARCHAR(255);
  SET cat_name = '';

  SET max_sp_recursion_depth=10;

  call doc_category_tree(cat_id, cat_name);

  RETURN cat_name;
END;

CREATE PROCEDURE doc_category_tree(IN cat_id bigint, OUT cat_name VARCHAR(255))
BEGIN
  DECLARE CURSOR_CAT_NAME VARCHAR(255);
    DECLARE CURSOR_CAT_PARENT BIGINT;
  DECLARE done INT DEFAULT FALSE;
    DECLARE cursor_cat CURSOR FOR SELECT c.name, c.parent FROM categories c where c.id = cat_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN cursor_cat;
  
  FETCH cursor_cat INTO CURSOR_CAT_NAME,CURSOR_CAT_PARENT;
    
  CLOSE cursor_cat;

  IF (CURSOR_CAT_PARENT > 0) THEN
    call doc_category_tree(CURSOR_CAT_PARENT, cat_name);
    SET cat_name = CONCAT(CURSOR_CAT_NAME, '/', cat_name);
  ELSE
    SET cat_name = CURSOR_CAT_NAME;
  END IF;
END;