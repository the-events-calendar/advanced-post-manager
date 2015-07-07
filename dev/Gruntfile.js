module.exports = function(grunt) {
  'use strict';

  grunt.initConfig({
    glotpress_download: {
      core: {
        options: {
          url: 'http://translations.tri.be',
          domainPath: '../lang',
          slug: 'tribe-apm',
          textdomain: 'tribe-apm',
        },
      },
    },
    makepot: {
      options: {
        domainPath: '/../lang',
        type: 'wp-plugin',
      },
    },
  });

  grunt.loadNpmTasks('grunt-glotpress');
  grunt.loadNpmTasks('grunt-wp-i18n');
};