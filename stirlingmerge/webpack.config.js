const path = require('path')
const webpack = require('webpack')
const { VueLoaderPlugin } = require('vue-loader')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')

const isProd = process.env.NODE_ENV === 'production'

module.exports = {
	mode: isProd ? 'production' : 'development',
	devtool: false,
	entry: {
		'stirlingmerge-main':  './src/main.js',
		'stirlingmerge-admin': './src/admin-settings.js',
	},
	output: {
		path: path.resolve(__dirname, 'js'),
		filename: '[name].js',
		clean: true,
	},
	module: {
		rules: [
			{
				test: /\.vue$/,
				loader: 'vue-loader',
			},
			{
				test: /\.css$/,
				use: [MiniCssExtractPlugin.loader, 'css-loader'],
			},
		],
	},
	plugins: [
		new VueLoaderPlugin(),
		new MiniCssExtractPlugin({
			filename: '../css/[name].css',
		}),
		// Provide Buffer globally (needed by @nextcloud/files and string_decoder)
		new webpack.ProvidePlugin({
			Buffer: ['buffer', 'Buffer'],
		}),
	],
	resolve: {
		extensions: ['.js', '.vue'],
		fallback: {
			path:           require.resolve('path-browserify'),
			string_decoder: require.resolve('string_decoder/'),
			buffer:         require.resolve('buffer/'),
			stream:         false,
			util:           false,
			url:            false,
			querystring:    false,
		},
	},
	performance: {
		hints: false,
	},
}
