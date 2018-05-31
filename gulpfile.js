const gulp = require('gulp')

gulp.task('default', function() {
    return gulp.src(__dirname + '/site/**/*')
        .pipe(gulp.dest(__dirname + '/build'))
})