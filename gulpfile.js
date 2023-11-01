/*
 * Set the URL of your local instance of Telemetry here.
 * Then run "gulp serve".
 * This is for local development purpose only.
 * Don't commit this change in the repository.
 */
const localServer = {
  url: 'http://telemetry.localhost/'
}

var gulp = require('gulp'),
  del = require('del'),
  uglify = require('gulp-uglify'),
  cleanCSS = require('gulp-clean-css'),
  merge = require('merge-stream'),
  concat = require('gulp-concat'),
  replace = require('gulp-replace'),
  browserSync = require('browser-sync').create()
  build = require('./semantic/tasks/build'),
  buildJS = require('./semantic/tasks/build/javascript'),
  buildCSS = require('./semantic/tasks/build/css'),
  buildAssets = require('./semantic/tasks/build/assets')
;

gulp.task('build ui', build);
gulp.task('build-css', buildCSS);
gulp.task('build-javascript', buildJS);
gulp.task('build-assets', buildAssets);

var paths = {
  webroot: './public/',
  assets: {
    public: './public/assets/',
    css: './public/assets/css/',
    js: './public/assets/js/',
    webfonts: './public/assets/webfonts/',
    theme: {
      public: './public/ui/',
      css: './public/ui/semantic.min.css',
      js: './public/ui/semantic.min.js'
    }
  },
  src: {
    semantic: './semantic.json',
    theme: './ui/semantic/galette/**/*',
    config: './ui/semantic/theme*',
    files: [
      './ui/semantic/galette/*',
      './ui/semantic/galette/**/*.*'
    ],
    css: './css/**/*.css',
    js: './js/*.js'
  },
  semantic: {
    src: './semantic/src/',
    theme: './semantic/src/themes/galette/'
  },
  scripts: {
    main: [
      './js/base.js'
    ],
    telemetry: [
      './js/references_countries.js',
      './js/telemetry.js'
    ],
    leaflet: [
      './node_modules/leaflet/dist/leaflet.js',
      './node_modules/leaflet.fullscreen/Control.FullScreen.js',
      './node_modules/leaflet-providers/leaflet-providers.js',
      './node_modules/spin.js/spin.js',
      './node_modules/leaflet-spin/leaflet.spin.js'
    ]
  },
  styles: {
    leaflet: [
      './node_modules/leaflet/dist/leaflet.css',
      './node_modules/leaflet.fullscreen/Control.FullScreen.css',
    ]
  },
  extras: [
    {
      src: [
          './node_modules/jquery/dist/jquery.min.js',
          './node_modules/masonry-layout/dist/masonry.pkgd.min.js',
          './node_modules/plotly.js-dist/plotly.js',
      ],
      dest: 'js/'
    },
    {
      src: [
        './node_modules/leaflet.fullscreen/icon-fullscreen.svg'
      ],
      dest: 'images/'
    }
  ]
};

function theme() {
  config = gulp.src(paths.src.config)
    .pipe(gulp.dest(paths.semantic.src))
    .pipe(browserSync.stream());

  theme =  gulp.src(paths.src.files)
    .pipe(gulp.dest(paths.semantic.theme))
    .pipe(browserSync.stream());

  return merge(config, theme);
}

function clean() {
  return del([
    paths.assets.public,
    paths.assets.theme.public,
  ]);
}

function styles() {
  leaflet = gulp.src(paths.styles.leaflet)
    .pipe(replace('icon-fullscreen.svg', '../images/icon-fullscreen.svg'))
    .pipe(cleanCSS())
    .pipe(concat('leaflet.bundle.min.css'))
    .pipe(gulp.dest(paths.assets.css))
    .pipe(browserSync.stream());

  return merge(leaflet);
};

function scripts() {
  main = gulp.src(paths.scripts.main)
    .pipe(concat('main.bundle.min.js'))
    .pipe(uglify({
      output: {
        comments: /^!/
      }
    }))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  telemetry = gulp.src(paths.scripts.telemetry)
    .pipe(concat('telemetry.bundle.min.js'))
    .pipe(uglify({
      output: {
        comments: /^!/
      }
    }))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  leaflet = gulp.src(paths.scripts.leaflet)
    .pipe(concat('leaflet.bundle.min.js'))
    .pipe(uglify({
      output: {
        comments: /^!/
      }
    }))
    .pipe(gulp.dest(paths.assets.js))
    .pipe(browserSync.stream());

  return merge(main, telemetry, leaflet);
}

function movefiles() {
  extras = paths.extras.map(function (extra) {
    return gulp.src(extra.src)
      .pipe(gulp.dest(paths.assets.public + extra.dest))
      .pipe(browserSync.stream());
    }
  );

  return merge(extras);
}

exports.theme = theme;
exports.clean = clean;
exports.styles = styles;
exports.scripts = scripts;
exports.movefiles = movefiles;

var build = gulp.series(theme, clean, styles, scripts, movefiles, 'build ui');
exports.default = build;
