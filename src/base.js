Ext.Base = Ext.extend(Object, {
	// autostart flag
	autoStart: false,
	// stage width
	sW: undefined,
	// stage height
	sH: undefined,
	// get the mouse automaticaly
	autoMouse: true,
	
	showFps: false,
	
	constructor: function(config) {
		Ext.initialConfig = config;
		Ext.apply(this, config || {});
		
		var me = this;
		
		me.mouse = {
			x: 0,
			y: 0
		};
		
		me.initScene();
		me.setEvents();
		me.autoStart && me.start();
		
		me.stage.add(me.layer);
	},
	
	/**
	 * initScene: build your scene here
	 * @return undefined
	 */
	initScene: function() {
		var me = this;
		me.sW = me.stage.getWidth();
		me.sH = me.stage.getHeight();
	},
	
	/**
	 * setEvents: set the scene events
	 * @return undefined
	 */
	setEvents: function() {},
	
	start: function() {
		var me = this;
		me.animation && me.animation.stop();
		me.mouse = me.mouse || {x: 0, y: 0};
		me.animation = new Kinetic.Animation(function() {
			if(me.autoMouse) me.mouse = me.stage.getPointerPosition() || me.mouse;
			if(me.showFps) {
				me.updateFps.apply(me, arguments);
			}
			me.onFrame.apply(me, arguments);
		}, me.layer);
		me.animation.start();
	},
	
	fpsTimeStamp: 0,
	fpsCount: 0,
	updateFps: function(frame) {
		var me = this;
		if(Date.now() - me.fpsTimeStamp < 1000) {
			me.fpsCount++;
			return;
		}
		me.fpsTimeStamp = Date.now();
		
		console.log(me.fpsCount);
		
		me.fpsCount = 0;
	},
	
	stop: function() {
		var me = this;
		me.animation && me.animation.stop();
	},
	
	onFrame: function(frame) {
		
	},
	
	/**
     * Bind event on a specific element
     * @param	{Object} target
     * @param	{String} event
     * @param	{Object} callback
     * @return	{void}
     */
	bind: function(target, event, method) {
		this.handlers = this.handlers || {};
		this.bindedObjects = this.bindedObjects || [];

		var self = this,
		t = $(target);
		
		this.handlers[method] = function() {
			return method.apply(self, Array.prototype.slice.call(arguments).concat([this]));
		}
			
		t.on(event, this.handlers[method]);
		this.bindedObjects.push( {
				target	: t,
				method	: method,
				event	: event
			}
		);
	},
	
	/**
     * Unbind event on a specific element
     * @param	{Object} target
     * @param	{String} event
     * @param	{Object} callback
     * @return	{void}
     */
	unbind: function(target, event, method) {
		$(target).off(event, this.handlers[method]);
		//this.handlers[method] = null;
	},
	
	/**
     * Unbind all event from daugther class
     * @return	{void}
     */
	unbindAll: function() {
		var self = this;
		Ext.each(
			this.bindedObjects, function(item, idx, allItems) {
				self.unbind(item.target, item.event, item.method);
			},
			this
		);
	}
});