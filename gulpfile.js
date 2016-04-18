var elixir = require('laravel-elixir');
var autoprefixer = require('autoprefixer');

elixir.config.css.autoprefix.options.browsers = ["> 2%"];
elixir.config.css.autoprefix.options.flexbox = "no-2009";
elixir.config.css.cssnano.pluginOptions.discardComments = {removeAll: true};
//elixir.config.js.uglify.options.compress.drop_console = false;

elixir.config.assetsPath='.';
elixir.config.publicPath='.';

elixir(function (mix) {
	// Compile CSS
	mix.sass('sass/app.scss', 'css/app-3.css');

	// JS libs
	mix.scripts([
		'js-src/lib/lodash.custom.js',
		'js-src/lib/jquery.js'
	], 'js/libs-1.js');

	mix.scripts([
		'js-src/app.js'
	], 'js/app-2.js');
});
