<template>
  <div>

    <div v-show="error" class="form-modal-error">
      <b>Folgende Fehler sind aufgetreten:</b>
      <div>{{error}}</div>
    </div>

    <div v-show="succeed" class="form-modal-succeed">
      <b>{{succeed}}</b>
    </div>

    <div v-if="loading == true" class="overlay">
      <i class="fa fas fa-sync-alt fa-spin"></i>
    </div>

    <h3 class="margin-b-l"><i class="fa fa-sliders-h"></i> Einstellungen</h3>

    <ul class="noListStyle padding-t-l form-style-2">

      <li v-bind:key="index" v-for="(item, index) in settings"
        class="padding-t-m  padding-b-m line-oddEven">

        <Boolean
          v-if="item.typ == 'BOOLEAN'"
          v-bind:item="item"
          v-on:change="triggerToggleValue"></Boolean>
        
        <Number
          v-if="item.typ == 'NUMBER'"
          v-bind:item="item"
          v-on:change="triggerToggleValue"></Number>

        <String
          v-if="item.typ == 'STRING'"
          v-bind:item="item"
          v-on:change="triggerToggleValue"></String>
        
        <Select
          v-if="item.typ == 'SELECT'"
          v-bind:item="item"
          v-on:change="triggerToggleValue"></Select>

      </li>
    </ul>

  </div>
</template>

<script>

const axios = require('axios').default;

import Boolean from './components/boolean.vue'
import Number from './components/number.vue'
import String from './components/string.vue'
import Select from './components/select.vue'

export default {
  components: {
    Boolean,
    Number,
    String,
    Select
  },
  data() {
    return {
      selfURL: globals.selfURL,
      error: false,
      succeed: false,
      loading: false,

      settings: globals.settings
    };
  },
  created: function () {

  },
  methods: {

    triggerToggleValue(obj) {
      //console.log('triggerToggleEvent',obj);

      obj.item.value = obj.value;

      this.saveData(obj);
    },

    saveData: function (obj) {

      
      var that = this;


      if (this.loading == false) {

        this.loading = true;

        this.ajaxPost(
          this.selfURL+'&task=save',
          { settings: this.settings },
          { },
          function (response, that) {
            
            //console.log(response);

            if ( response.data ) {
              that.succeed = 'Einstellungen wurden erfolgreich gespeichert!!';
              that.error = false;
            } else {
              if (response.data.error) {
                that.error = response.data.error;
                that.succeed = false;
              } else {
                that.error = 'Fehler beim Laden. 01';
                that.succeed = false;
              }
              
            }

          },
          function () {
            that.error = 'Fehler beim Laden. 02';
            that.succeed = false;
          },
          function () {
            that.loading = false;
          }
        );

      }

    },

    ajaxPost: function (url, data, params, callback, error, allways) {
      this.loading = true;
      var that = this;
      axios.post(url, data, {
        params: params
      })
      .then(function (response) {
        // console.log(response.data);
        if (callback && typeof callback === 'function') {
          callback(response, that);
        }
      })
      .catch(function (resError) {
        //console.log(error);
        if (resError && typeof error === 'function') {
          error(resError);
        }
      })
      .finally(function () {
        // always executed
        if (allways && typeof allways === 'function') {
          allways();
        }
        that.loading = false;
      });  
      
    }
    
  }

};
</script>

<style>

</style>