<template>
  <div class="form-style-2">

    <div class="flex-row">
      <div class="flex-1">
        <button class="btn btn-grey-line" v-on:click="handlerBack"> Zurück</button>
      </div>
      <div v-show="item.id" class="flex flex-end">
        <button v-show="deleteItem == false" v-on:click="handlerDelete(item)" class="btn btn-grey-line"><i class="far fa-trash-alt"></i> Löschen</button>
        <button v-show="deleteItem" v-on:click="handlerDeleteSure(item)" class="btn btn-red"><i class="far fa-trash-alt"></i>Löschen!</button>
      </div>
    </div>

    <br><br>
    <ul class="noListStyle">
      <li class="line-oddEven padding-t-m padding-b-l padding-l-l">
        <h3 class=""></span>Menü Name</h3>
        <input type="text" v-model="item.title" class="width-40vw" />
      </li>
      <li class="line-oddEven padding-t-m padding-b-m">
        <label class="width-12rem padding-l-l">Gruppe</label>
        <input type="text" readonly class="select readonly width-20vw" :value="item.parent_title" v-on:click="handlerParentOpen" />
      </li>
      <li class="line-oddEven"></li>
      <li v-show="parentOpen" class="line-oddEven padding-t-m padding-b-m padding-l-l">
        <div  class="parent">
          <h4>Menu</h4>
          <ul v-bind:key="index" v-for="(menu_item, index) in items" class="noListStyle" :value="item.id">
            <li class="margin-b-s">
              <button class="btn btn-grau"
                      :class="{'btn-orange': menu_item.id == item.parent_id }"
                      v-on:click="handlerParentSelect(menu_item)"><i :class="menu_item.icon"></i> {{menu_item.title}}</button>
              <ul v-if="menu_item.items.length >= 1" class="noListStyle flex-row">
                <li v-bind:key="i" v-for="(child, i) in menu_item.items" :value="child.id"  class="margin-b-s padding-l-l margin-t-s">
                  <button class="btn btn-grau"
                          :class="{'btn-orange': child.id == item.parent_id }"
                          v-on:click="handlerParentSelect(child)" ><i :class="child.icon"></i> {{child.title}}</button>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </li>
      <li class="line-oddEven padding-t-m padding-b-m">
        <label class="width-12rem padding-l-l">Seite</label>
        <input type="text" :value="item.page" readonly class="select readonly width-20vw" v-on:click="handlerPagesOpen"/>
        <span v-if="item.params" class="padding-l-s text-grey">Params: {{item.params}}</span>
      </li>
      <li class="line-oddEven"></li>
      <li v-show="pagesOpen" class="line-oddEven padding-t-m padding-b-m  padding-l-l">
          <div v-bind:key="index" v-for="(item, index) in pages" class="">
            <h4>{{item.name}}</h4>
            <div class="flex-row">
              <span v-if="item.submenu" v-bind:key="index" v-for="(page, i) in item.submenu" class="margin-b-s" >
                <button v-if="page.menu != false" class="btn btn-grau margin-r-m" :class="{'btn-grey-line': page.admin == true}" v-on:click="handlerPagesSelect(page)"><i :class="page.icon"></i>{{page.title}}</button>
              </span>
            </div>
          </div>
      </li>
      <li class="line-oddEven padding-t-m padding-b-m">
        <label class="width-12rem padding-l-l">Icon</label>
        <input type="text" v-model="item.icon" class="width-20vw" />
      </li>
      <li>
        <br>
        <button class="btn btn-blau" v-on:click="handlerSubmit"><i class="fas fa-mouse-pointer"></i> Speichern</button>
      </li>
    </ul>


  </div>
</template>

<script>



export default {
  components: {

  },
  props: {
    item: Array,
    pages: Array,
    items: Array
  },
  data() {
    return {
      deleteItem: false,
      pagesOpen: false,
      parentOpen: false
    };
  },
  created: function () {
  },
  methods: {

    handlerParentOpen: function () {
      if( this.parentOpen ) {
        this.parentOpen = false;
      } else {
        this.parentOpen = true;
      }
    },
    handlerParentSelect: function (item) {

      if (item.id && item.title) {
        this.item.parent_id = item.id;
        this.item.parent_title = item.title;
      }
      this.parentOpen = false;

    },
    handlerPagesSelect: function (page) {
      if (!page.title || !page.url) {
        return false
      }
      this.item.page = page.url.page;
      this.item.params = JSON.stringify(page.url.params);
      this.pagesOpen = false;
    },
    handlerPagesOpen: function () {
      if( this.pagesOpen ) {
        this.pagesOpen = false;
      } else {
        this.pagesOpen = true;
      }
    },
    handlerSubmit: function () {

      if (!this.item.title) {
        return false;
      }
      this.deleteItem = false;
      EventBus.$emit('item-form--submit', {
        item: this.item
      });


    },
    handlerBack: function () {
      this.deleteItem = false;
      this.pagesOpen = false;
      EventBus.$emit('show--set', {
        'show': 'items'
      });
    },
    handlerDelete: function (item) {
      if (!item.id) {
        return false;
      }
      this.deleteItem = item;
      this.pagesOpen = false;

    },
    handlerDeleteSure: function () {
      if (!this.item.id) {
        return false;
      }
      this.deleteItem = false;
      this.pagesOpen = false;
      EventBus.$emit('item-form--delete', {
        item: this.item
      });
    }

  }

};
</script>

<style>
</style>