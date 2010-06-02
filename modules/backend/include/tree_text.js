/*
    TreeText 0.0.1
    Copyright (c) 2008. by MeanEYE [RCF]
    http://rcf-group.com
*/

function ttext_ShowAll(name_base, class_base, tag) {
   var items = document.getElementsByTagName(tag);

   if (items.length > 0) {
      for (i=0; i<items.length; i++) {
         item_name = items[i].id;

         if (item_name.substring(0, name_base[0].length) == name_base[0])
            items[i].className = class_base[0] + '_selected';

         if (item_name.substring(0, name_base[1].length) == name_base[1])
            items[i].className = class_base[1] + '_selected';
      }
   }
}

function ttext_HideAll(name_base, class_base, tag) {
   var items = document.getElementsByTagName(tag);

   if (items.length > 0) {
      for (i=0; i<items.length; i++) {
         item_name = items[i].id;

         if (item_name.substring(0, name_base[0].length) == name_base[0])
            items[i].className = class_base[0];

         if (item_name.substring(0, name_base[1].length) == name_base[1])
            items[i].className = class_base[1];
      }
   }
}

function ttext_Show(name_base, class_base, id) {
   var obj_header = document.getElementById(name_base[0]+id);
   var obj_text = document.getElementById(name_base[1]+id);

   obj_header.className = class_base[0] + '_selected';
   obj_text.className = class_base[1] + '_selected';
}

function ttext_Hide(name_base, class_base, id) {
   var obj_header = document.getElementById(name_base[0]+id);
   var obj_text = document.getElementById(name_base[1]+id);

   obj_header.className = class_base[0];
   obj_text.className = class_base[1];
}

function ttext_Toggle(name_base, class_base, id) {
   var obj_header = document.getElementById(name_base[0]+id);
   var obj_text = document.getElementById(name_base[1]+id);

   if (obj_header.className == class_base[0] + '_selected')
      ttext_Hide(name_base, class_base, id); else
      ttext_Show(name_base, class_base, id);
}

function ttext_SwitchOne(name_base, class_base, id, tag) {
   var obj_header = document.getElementById(name_base[0]+id);

   if (obj_header.className == class_base[0] + '_selected') {
      ttext_Hide(name_base, class_base, id);
   } else {
      ttext_HideAll(name_base, class_base, tag);
      ttext_Show(name_base, class_base, id);
   }
}

function ttext_SwitchThisOne(name_base, class_base, obj_header, tag) {
   var id = obj_header.id.substr(name_base[0].length);
   
   if (obj_header.className == class_base[0] + '_selected') {
      ttext_Hide(name_base, class_base, id);
   } else {
      ttext_HideAll(name_base, class_base, tag);
      ttext_Show(name_base, class_base, id);
   }
}